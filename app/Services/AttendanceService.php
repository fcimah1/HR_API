<?php

namespace App\Services;

use App\DTOs\Attendance\AttendanceFilterDTO;
use App\DTOs\Attendance\CreateAttendanceDTO;
use App\DTOs\Attendance\UpdateAttendanceDTO;
use App\DTOs\Attendance\AttendanceResponseDTO;
use App\DTOs\Attendance\GetAttendanceDetailsDTO;
use App\Enums\AttendanceStatusEnum;
use App\Enums\AttendenceStatus;
use App\Enums\PunchTypeEnum;
use App\Enums\VerifyModeEnum;
use App\Models\User;
use App\Repository\Interface\AttendanceRepositoryInterface;
use App\Repository\Interface\UserRepositoryInterface;
use App\Services\SimplePermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AttendanceService
{
    public function __construct(
        protected AttendanceRepositoryInterface $attendanceRepository,
        protected SimplePermissionService $permissionService,
        protected HolidayService $holidayService,
        protected NotificationService $notificationService,
        protected UserRepositoryInterface $userRepository,
        protected CacheService $cacheService,
    ) {}

    /**
     * Get paginated attendance records with filters
     */
    public function getAttendanceRecords(AttendanceFilterDTO $filters, User $user): array
    {
        $filterData = [];

        // Get effective company ID
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
        $filterData['company_id'] = $effectiveCompanyId;

        // If specific employee requested, check permission
        if ($filters->employeeId !== null) {
            // Company admins or users with 'timesheet' can see anyone
            $canViewAll = $user->user_type === 'company' || $this->permissionService->checkPermission($user, 'timesheet');

            if (!$canViewAll && $filters->employeeId !== $user->user_id) {
                throw new \Exception('ليس لديك صلاحية لعرض سجلات حضور موظف آخر');
            }
            $filterData['employee_id'] = $filters->employeeId;
        } else {
            // If no employee specified:
            // - Company/Admins/ViewAll: See all (no employee_id filter)
            // - Regular Staff: See only their records
            $canViewAll = $user->user_type === 'company' || $this->permissionService->checkPermission($user, 'timesheet');

            if (!$canViewAll) {
                $filterData['employee_id'] = $user->user_id;
            }
        }

        // Apply other filters
        $filterData['from_date'] = $filters->fromDate;
        $filterData['to_date'] = $filters->toDate;
        $filterData['status'] = $filters->status;
        $filterData['work_from_home'] = $filters->workFromHome;
        $filterData['per_page'] = $filters->perPage;
        $filterData['page'] = $filters->page;

        $updatedFilters = AttendanceFilterDTO::fromRequest($filterData, $effectiveCompanyId);
        $records = $this->attendanceRepository->getPaginatedRecords($updatedFilters);

        $data = collect($records->items())->map(function ($attendance) {
            return AttendanceResponseDTO::fromModel($attendance, true)->toArray();
        });

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
                'has_more_pages' => $records->hasMorePages(),
            ]
        ];
    }

    /**
     * Clock in
     */
    public function clockIn(CreateAttendanceDTO $dto): array
    {
        return DB::transaction(function () use ($dto) {

            // Check if already clocked in today
            if ($this->attendanceRepository->hasClockedInToday($dto->employeeId)) {
                throw new \Exception('لقد سجلت الحضور اليوم بالفعل');
            }

            // Check for holidays
            if ($this->holidayService->isHoliday($dto->attendanceDate, $dto->companyId)) {
                $holiday = $this->holidayService->getHolidayForDate($dto->attendanceDate, $dto->companyId);
                throw new \Exception('لا يمكن تسجيل الحضور في يوم عطلة: ' . ($holiday['event_name'] ?? 'عطلة رسمية'));
            }

            $attendance = $this->attendanceRepository->clockIn($dto);

            Log::info('Clock in completed', [
                'attendance_id' => $attendance->time_attendance_id,
                'employee_id' => $attendance->employee_id,
            ]);

            return AttendanceResponseDTO::fromModel($attendance, true)->toArray();
        });
    }

    /**
     * Create manual attendance record (Admin)
     */
    public function createManualAttendance(array $data, int $companyId): array
    {
        return DB::transaction(function () use ($data, $companyId) {
            $employeeId = $data['employee_id'];
            $startDate = Carbon::parse($data['start_attendance_date']);
            $endDate = Carbon::parse($data['end_attendance_date']);

            if ($endDate->lt($startDate)) {
                throw new \Exception('تاريخ النهاية يجب أن يكون بعد تاريخ البداية');
            }

            $period = CarbonPeriod::create($startDate, $endDate);
            $createdAttendances = [];
            $skippedDates = [];

            foreach ($period as $date) {
                $currentDate = $date->format('Y-m-d');

                // Check if attendance already exists for this date
                $existingAttendance = $this->attendanceRepository->findTodayAttendance($employeeId, $currentDate);
                if ($existingAttendance) {
                    // Skip if attendance already exists for this day
                    $skippedDates[] = $currentDate;
                    continue;
                }

                // Construct DateTime for Clock In/Out (Request has H:i:s)
                $clockInTime = $data['clock_in'] ?? null;
                $clockOutTime = $data['clock_out'] ?? null;

                // If clock_in is missing but shift is provided, fetch from shift
                $shift = null;
                if (!empty($data['office_shift_id'])) {
                    $shift = \App\Models\OfficeShift::find($data['office_shift_id']);
                }

                if (empty($clockInTime) && $shift) {
                    $shiftTimes = $shift->getShiftTimesForDay($date->format('l'));
                    $clockInTime = $shiftTimes['in_time'];
                    $clockOutTime = $shiftTimes['out_time'];
                }

                // If clockInTime is still empty, it's likely a day off or invalid shift time
                if (empty($clockInTime)) {
                    $skippedDates[] = $currentDate . ' (Day Off)';
                    continue;
                }

                // Ensure seconds are present in times (some DBs/Logs expect H:i:s)
                if ($clockInTime && strlen($clockInTime) == 5) {
                    $clockInTime .= ':00';
                }
                if ($clockOutTime && strlen($clockOutTime) == 5) {
                    $clockOutTime .= ':00';
                }

                $clockInDateTime = $clockInTime ? $currentDate . ' ' . $clockInTime : null;
                $clockOutDateTime = $clockOutTime ? $currentDate . ' ' . $clockOutTime : null;

                $totalWork = '00:00';
                $timeLate = '00:00';
                $earlyLeaving = '00:00';
                $overtime = '00:00';

                if ($clockInDateTime && $clockOutDateTime) {
                    $totalWork = $this->calculateTotalWorkHours(
                        $clockInDateTime,
                        $clockOutDateTime
                    );
                }

                // Calculate Late, Early Leaving, Overtime if Shift is present, otherwise use timestamps
                if ($shift) {
                    if ($clockInDateTime) {
                        $timeLate = $shift->calculateTimeLate($currentDate, $clockInDateTime);
                    }
                    if ($clockOutDateTime) {
                        $earlyLeaving = $shift->calculateEarlyLeaving($currentDate, $clockOutDateTime);
                        $overtime = $shift->calculateOvertime($currentDate, $clockOutDateTime);
                    }
                } else {
                    // If no shift, store the actual timestamps
                    $timeLate = $clockInDateTime ?? '00:00';
                    $earlyLeaving = $clockOutDateTime ?? '00:00';
                    $overtime = $clockOutDateTime ?? '00:00';
                }

                // Prepare data for DTO
                $dayData = $data;
                $dayData['attendance_date'] = $currentDate;
                $dayData['clock_in'] = $clockInDateTime;
                $dayData['clock_out'] = $clockOutDateTime;
                $dayData['time_late'] = $timeLate;
                $dayData['early_leaving'] = $earlyLeaving;
                $dayData['overtime'] = $overtime;
                $dayData['total_work'] = $totalWork;

                $dto = CreateAttendanceDTO::fromManualRequest(
                    $dayData,
                    $companyId
                );

                $attendance = $this->attendanceRepository->clockIn($dto);

                Log::info('Manual attendance created', [
                    'attendance_id' => $attendance->time_attendance_id,
                    'employee_id' => $employeeId,
                    'date' => $currentDate,
                    'created_by' => Auth::id(),
                ]);

                $createdAttendances[] = AttendanceResponseDTO::fromModel($attendance, true)->toArray();
            }

            return [
                'created_attendances' => $createdAttendances,
                'skipped_dates' => $skippedDates
            ];
        });
    }

    /**
     * Clock out
     */
    public function clockOut(int $userId, string $ipAddress, ?string $latitude = null, ?string $longitude = null): array
    {
        return DB::transaction(function () use ($userId, $ipAddress, $latitude, $longitude) {
            // Find today's attendance
            $attendance = $this->attendanceRepository->findTodayAttendance($userId);

            if (!$attendance) {
                throw new \Exception('يجب تسجيل الحضور أولاً قبل تسجيل الانصراف');
            }

            if ($attendance->clock_out) {
                throw new \Exception('لقد سجلت الانصراف بالفعل');
            }

            // Calculate total work hours
            $totalWork = $this->calculateTotalWorkHours(
                $attendance->clock_in,
                now()->format('Y-m-d H:i:s'),
                $attendance->lunch_breakin,
                $attendance->lunch_breakout
            );

            $dto = UpdateAttendanceDTO::fromClockOutRequest(
                ['latitude' => $latitude, 'longitude' => $longitude],
                $ipAddress,
                $totalWork
            );

            $updatedAttendance = $this->attendanceRepository->clockOut($attendance, $dto);

            Log::info('Clock out completed', [
                'attendance_id' => $updatedAttendance->time_attendance_id,
                'total_work' => $totalWork,
            ]);

            return AttendanceResponseDTO::fromModel($updatedAttendance, true)->toArray();
        });
    }

    /**
     * Start lunch break
     */
    public function lunchBreakIn(int $userId, UpdateAttendanceDTO $dto): array
    {
        return DB::transaction(function () use ($userId, $dto) {
            $attendance = $this->attendanceRepository->findTodayAttendance($userId);

            if (!$attendance) {
                throw new \Exception('يجب تسجيل الحضور أولاً');
            }

            if ($attendance->lunch_breakin && !$attendance->lunch_breakout) {
                throw new \Exception('لقد بدأت استراحة الغداء بالفعل');
            }

            $updatedAttendance = $this->attendanceRepository->lunchBreakIn($attendance, $dto);

            return AttendanceResponseDTO::fromModel($updatedAttendance)->toArray();
        });
    }

    /**
     * End lunch break
     */
    public function lunchBreakOut(int $userId, UpdateAttendanceDTO $dto): array
    {
        return DB::transaction(function () use ($userId, $dto) {
            $attendance = $this->attendanceRepository->findTodayAttendance($userId);

            if (!$attendance) {
                throw new \Exception('يجب تسجيل الحضور أولاً');
            }

            if (!$attendance->lunch_breakin) {
                throw new \Exception('يجب بدء استراحة الغداء أولاً');
            }

            if ($attendance->lunch_breakout) {
                throw new \Exception('لقد أنهيت استراحة الغداء بالفعل');
            }

            $updatedAttendance = $this->attendanceRepository->lunchBreakOut($attendance, $dto);

            return AttendanceResponseDTO::fromModel($updatedAttendance)->toArray();
        });
    }

    /**
     * Get today's attendance status
     */
    /**
     * Get today's attendance status
     */
    public function getAttendanceByDay(User $currentUser, array $data): array
    {
        $targetId = $currentUser->user_id;
        $targetEmployeeId = $data['employee_id'];
        $attendanceDate = $data['attendance_date'];

        if ($targetEmployeeId !== null && $targetEmployeeId !== $currentUser->user_id) {
            // Check permissions
            // Company admins or users with 'timesheet' can see anyone (as per user request)
            $canViewAll = $currentUser->user_type === 'company' || $this->permissionService->checkPermission($currentUser, 'timesheet');

            if (!$canViewAll) {
                throw new \Exception('ليس لديك صلاحية لعرض حالة حضور موظف آخر');
            }
            $targetId = $targetEmployeeId;
        }

        $attendance = $this->attendanceRepository->findTodayAttendance($targetId, $attendanceDate);

        if (!$attendance) {
            return [
                'success' => false,
                'message' => 'لا يوجد سجل حضور لهذا اليوم'
            ];
        }

        return [
            'has_clocked_in' => true,
            'has_clocked_out' => !empty($attendance->clock_out),
            'on_lunch_break' => !empty($attendance->lunch_breakin) && empty($attendance->lunch_breakout),
            'attendance' => AttendanceResponseDTO::fromModel($attendance)->toArray(),
        ];
    }

    /**
     * Update attendance record (admin/manager only)
     */
    public function updateAttendance(int $id, UpdateAttendanceDTO $dto, User $user): array
    {
        return DB::transaction(function () use ($id, $dto, $user) {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $attendance = $this->attendanceRepository->findAttendanceInCompany($id, $effectiveCompanyId);

            if (!$attendance) {
                throw new \Exception('سجل الحضور غير موجود');
            }

            // Recalculate work hours if clock_in or clock_out is changed
            // Need to handle potential time changes
            $clockIn = $dto->clockIn ?? $attendance->clock_in;
            $clockOut = $dto->clockOut ?? $attendance->clock_out;

            // If times are changed, we should recalculate total_work
            if (($dto->clockIn || $dto->clockOut) && $clockIn && $clockOut) {
                $totalWork = $this->calculateTotalWorkHours($clockIn, $clockOut);

                // Create a new DTO with updated total_work (and ensuring other fields are preserved)
                // Since DTO is immutable, we create a new one from array
                $updateData = $dto->toArray();
                $updateData['clock_in'] = $clockIn;
                $updateData['clock_out'] = $clockOut;
                $updateData['total_work'] = $totalWork;
                // Preserve other fields if they were set in original DTO

                // We need to pass all fields to fromUpdateRequest?
                // fromUpdateRequest takes an array and maps keys.
                // keys in toArray match keys expected by fromUpdateRequest (mostly).

                // updateData has 'clock_in', 'clock_out', 'total_work' etc.
                // fromUpdateRequest expects 'clock_in', 'clock_out', 'total_work', 'status', 'early_leaving', 'overtime', 'shift_id', 'attendance_status'

                // Let's ensure keys match.
                // toArray keys: 'clock_in', 'clock_out', 'clock_out_ip_address', 'clock_out_latitude', 'clock_out_longitude', 'total_work', 'status', 'early_leaving', 'overtime', 'office_shift_id', 'attendance_status'

                // fromUpdateRequest expects: 'shift_id' (mapped from 'office_shift_id'?), 'attendance_status'.
                // fromUpdateRequest maps: 'shift_id' => $data['shift_id'].
                // toArray outputs: 'office_shift_id'.

                // Mismatch here!
                // toArray is used for Eloquent update, so it uses DB column names.
                // fromUpdateRequest is used for Request data, so it uses Request field names.

                // I should reconcile this.
                // I'll map 'office_shift_id' back to 'shift_id' for DTO creation.
                if (isset($updateData['office_shift_id'])) {
                    $updateData['shift_id'] = $updateData['office_shift_id'];
                }

                $dto = UpdateAttendanceDTO::fromUpdateRequest($updateData);
            }

            $updatedAttendance = $this->attendanceRepository->updateAttendance($attendance, $dto);

            return AttendanceResponseDTO::fromModel($updatedAttendance, true)->toArray();
        });
    }

    /**
     * Delete attendance record (admin/manager only)
     */
    public function deleteAttendance(int $id, User $user): bool
    {
        return DB::transaction(function () use ($id, $user) {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $attendance = $this->attendanceRepository->findAttendanceInCompany($id, $effectiveCompanyId);

            if (!$attendance) {
                throw new \Exception('سجل الحضور غير موجود');
            }

            return $this->attendanceRepository->deleteAttendance($id);
        });
    }

    /**
     * Get attendance details for a specific employee and date
     */
    public function getAttendanceDetails(User $currentUser, GetAttendanceDetailsDTO $dto): ?array
    {
        if ($dto->userId !== $currentUser->user_id) {
            // Check permissions
            $canViewAll = $currentUser->user_type === 'company' || $this->permissionService->checkPermission($currentUser, 'timesheet');

            if (!$canViewAll) {
                throw new \Exception('ليس لديك صلاحية لعرض تفاصيل حضور موظف آخر');
            }
        }

        $attendance = $this->attendanceRepository->findTodayAttendance($dto->userId, $dto->date);

        if (!$attendance) {
            return null;
        }

        return AttendanceResponseDTO::fromModel($attendance, true)->toArray();
    }

    /**
     * Calculate total work hours excluding lunch break
     */
    private function calculateTotalWorkHours(
        string $clockIn,
        string $clockOut,
        ?string $lunchBreakIn = null,
        ?string $lunchBreakOut = null
    ): string {
        $start = new \DateTime($clockIn);
        $end = new \DateTime($clockOut);

        $interval = $start->diff($end);
        $totalMinutes = ($interval->h * 60) + $interval->i;

        // Subtract lunch break if taken
        if ($lunchBreakIn && $lunchBreakOut) {
            $breakStart = new \DateTime($lunchBreakIn);
            $breakEnd = new \DateTime($lunchBreakOut);
            $breakInterval = $breakStart->diff($breakEnd);
            $breakMinutes = ($breakInterval->h * 60) + $breakInterval->i;
            $totalMinutes -= $breakMinutes;
        }

        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * تسجيل البصمة من جهاز البصمة
     * Biometric punch from fingerprint device
     * 
     * @param int $companyId رقم الشركة
     * @param int $branchId رقم الفرع
     * @param string $employeeId رقم الموظف في جهاز البصمة
     * @param string $punchTime وقت البصمة
     * @param int|null $verifyMode طريقة التحقق
     * @param int|null $punchType نوع البصمة
     * @param int|null $workCode كود العمل
     * @return array
     */
    public function biometricPunch(int $companyId, int $branchId, string $employeeId, string $punchTime, ?int $verifyMode = null, ?int $punchType = null, ?int $workCode = null): array
    {
        return DB::transaction(function () use ($companyId, $branchId, $employeeId, $punchTime, $verifyMode, $punchType, $workCode) {
            // تنضيف البيانات
            $companyId = (int)$companyId;
            $branchId = (int)($branchId ?? 0);
            $employeeId = trim((string)$employeeId);

            // 1. البحث عن الموظف باستخدام المفتاح المركب
            $userDetails = $this->userRepository->getUserByCompositeKey($companyId, $branchId, $employeeId);

            if (!$userDetails) {
                Log::warning('Biometric punch - Employee not found', [
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'employee_id' => $employeeId,
                ]);
                throw new \Exception('الموظف غير موجود في النظام');
            }

            $userId = $userDetails->user_id;
            $punchDate = date('Y-m-d', strtotime($punchTime));

            // إضافة أوصاف الحقول الجديدة
            $verifyModeText = $verifyMode !== null ? VerifyModeEnum::tryFrom($verifyMode)?->labelAr() ?? 'غير محدد' : 'غير محدد';
            $punchTypeText = $punchType !== null ? PunchTypeEnum::tryFrom($punchType)?->labelAr() ?? 'غير محدد' : 'غير محدد';

            // ============ التحققات الأساسية ============

            // متغيرات للحضور في العطلات
            $isHolidayWork = false;
            $holidayWorkReason = null;

            // 1. التحقق من العطلات الرسمية - السماح بالتسجيل مع علامة
            try {
                if ($this->holidayService->isHoliday($punchDate, $companyId)) {
                    $holiday = $this->holidayService->getHolidayForDate($punchDate, $companyId);
                    $isHolidayWork = true;
                    $holidayWorkReason = 'عطلة رسمية: ' . ($holiday['event_name'] ?? 'عطلة');

                    Log::info('Biometric punch on official holiday', [
                        'user_id' => $userId,
                        'punch_date' => $punchDate,
                        'holiday' => $holiday['event_name'] ?? 'عطلة',
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Holiday check failed in biometric punch', [
                    'user_id' => $userId,
                    'punch_date' => $punchDate,
                    'company_id' => $companyId,
                    'error' => $e->getMessage(),
                ]);

                // إيقاف العملية بالكامل عند فشل التحقق من العطلات
                throw new \Exception('فشل التحقق من بيانات العطلات الرسمية. يرجى التواصل مع الدعم الفني.');
            }

            // 2. التحقق من الإجازات المعتمدة - السماح بالتسجيل مع علامة
            $approvedLeave = \App\Models\LeaveApplication::forEmployee($userId)
                ->forCompany($companyId)
                ->approved()
                ->whereRaw("? BETWEEN from_date AND to_date", [$punchDate])
                ->first();

            if ($approvedLeave) {
                $isHolidayWork = true;
                $holidayWorkReason = 'إجازة معتمدة: ' . ($approvedLeave->leaveType->leave_type ?? 'إجازة');

                Log::info('Biometric punch during approved leave', [
                    'user_id' => $userId,
                    'punch_date' => $punchDate,
                    'leave_id' => $approvedLeave->leave_app_id,
                ]);
            }

            // 3. الحصول على بيانات الشيفت
            $officeShift = null;
            $timeLate = '00:00';
            $earlyLeaving = '00:00';
            $overtime = '00:00';

            // جلب بيانات الفرع للإحداثيات (مع Cache)
            $branch = $this->cacheService->getBranch($branchId);

            if ($userDetails->office_shift_id) {
                $officeShift = $this->cacheService->getOfficeShift($userDetails->office_shift_id);

                if ($officeShift) {
                    // 4. التحقق من أن اليوم ليس يوم عطلة أسبوعية - السماح بالتسجيل مع علامة
                    if ($officeShift->isDayOff($punchDate)) {
                        $isHolidayWork = true;
                        $holidayWorkReason = $holidayWorkReason ?? 'عطلة أسبوعية';

                        Log::info('Biometric punch on weekly day off', [
                            'user_id' => $userId,
                            'punch_date' => $punchDate,
                        ]);
                    }

                    // حساب التأخير للحضور (فقط إذا لم يكن يوم عطلة)
                    if (!$isHolidayWork) {
                        $timeLate = $officeShift->calculateTimeLate($punchDate, $punchTime);
                    }
                }
            }

            // 2. البحث عن سجل الحضور لهذا اليوم
            $attendance = $this->attendanceRepository->findTodayAttendance($userId, $punchDate);

            // 3. التعامل مع نوع البصمة بناءً على punch_type
            // 0 = حضور (Check-In)
            // 1 = انصراف (Check-Out)
            // 3 = بداية استراحة (Break In / Lunch Break Start)
            // 2 = نهاية استراحة (Break Out / Lunch Break End)
            // 4 = حضور عمل إضافي (Overtime In)
            // 5 = انصراف عمل إضافي (Overtime Out)

            $baseResponseData = [
                'user_id' => $userId,
                'employee_id' => $employeeId,
                'branch_id' => $branchId,
                'punch_time' => $punchTime,
                'verify_mode' => $verifyMode,
                'verify_mode_text' => $verifyModeText,
                'punch_type' => $punchType,
                'punch_type_text' => $punchTypeText,
                'work_code' => $workCode,
            ];

            switch ($punchType) {
                case 0: // Check-In (حضور)
                    if ($attendance) {
                        throw new \Exception('تم تسجيل الحضور لهذا اليوم بالفعل');
                    }

                    // تحديد حالة الحضور بناءً على العطلة
                    $attendanceStatus = $isHolidayWork ? 'Holiday Work' : 'Present';

                    Log::info('Biometric clock in', array_merge($baseResponseData, [
                        'time_late' => $timeLate,
                        'is_holiday_work' => $isHolidayWork,
                        'holiday_reason' => $holidayWorkReason,
                    ]));

                    $dto = new CreateAttendanceDTO(
                        companyId: $companyId,
                        branchId: $branchId,
                        employeeId: $userId,
                        attendanceDate: $punchDate,
                        clockIn: $punchTime,
                        clockInIpAddress: 'biometric',
                        clockInLatitude: null,
                        clockInLongitude: null,
                        shiftId: $userDetails->office_shift_id ?? 0,
                        workFromHome: 0,
                        attendanceStatus: $attendanceStatus,
                        status: $isHolidayWork ? 'Pending' : 'Approved', // الحضور في العطلة يحتاج مراجعة
                        timeLate: $timeLate,
                    );

                    $attendance = $this->attendanceRepository->clockIn($dto);

                    // إرسال تنبيه للمدير إذا كان حضور في يوم عطلة
                    if ($isHolidayWork) {
                        // البحث عن المستخدمين ذوي المستوى الأعلى في نفس الشركة
                        $employeeHierarchy = $userDetails->designation?->hierarchy_level ?? 5;

                        $higherLevelUsers = \App\Models\UserDetails::where('company_id', $companyId)
                            ->whereHas('designation', function ($query) use ($employeeHierarchy) {
                                $query->where('hierarchy_level', '<', $employeeHierarchy)
                                    ->whereNotNull('hierarchy_level');
                            })
                            ->pluck('user_id')
                            ->toArray();

                        // إذا لم يوجد مدراء بمستوى أعلى، أرسل للشركة
                        // إضافة الشركة دائماً للتنبيهات
                        $notifyUsers = array_merge($higherLevelUsers, [$companyId]);
                        $notifyUsers = array_unique($notifyUsers);

                        $this->notificationService->sendCustomNotification(
                            moduleOption: 'attendance',
                            moduleKeyId: (string) $attendance->time_attendance_id,
                            staffIds: $notifyUsers,
                            status: 'Pending'
                        );

                        Log::info('Holiday work notification sent to higher level', [
                            'attendance_id' => $attendance->time_attendance_id,
                            'employee_hierarchy' => $employeeHierarchy,
                            'notify_users' => $notifyUsers,
                            'reason' => $holidayWorkReason,
                        ]);
                    }

                    $responseMessage = $isHolidayWork
                        ? "تم تسجيل الحضور في عطلة ({$holidayWorkReason}) - بانتظار المراجعة"
                        : 'تم تسجيل الحضور بنجاح';

                    return [
                        'success' => true,
                        'type' => 'clock_in',
                        'message' => $responseMessage,
                        'is_holiday_work' => $isHolidayWork,
                        'holiday_reason' => $holidayWorkReason,
                        'data' => array_merge($baseResponseData, [
                            'attendance_id' => $attendance->time_attendance_id,
                            'time_late' => $timeLate,
                            'shift_in_time' => $officeShift?->getInTimeForDate($punchDate),
                            'branch_name' => $branch?->branch_name,
                            'attendance_status' => $attendanceStatus,
                        ])
                    ];

                case 1: // Check-Out (انصراف)
                    // السماح بالانصراف بدون حضور (للمراجعة من HR)
                    if (!$attendance) {
                        Log::warning('Biometric clock out without clock in', $baseResponseData);

                        // إنشاء سجل حضور جديد بدون clock_in للمراجعة من HR
                        $dto = new CreateAttendanceDTO(
                            companyId: $companyId,
                            branchId: $branchId,
                            employeeId: $userId,
                            attendanceDate: $punchDate,
                            clockIn: null, // بدون حضور
                            clockInIpAddress: null,
                            clockInLatitude: null,
                            clockInLongitude: null,
                            shiftId: $userDetails->office_shift_id ?? 0,
                            workFromHome: 0,
                            timeLate: null,
                        );

                        $attendance = $this->attendanceRepository->clockIn($dto);

                        // تسجيل الانصراف مباشرة
                        $updateDto = new UpdateAttendanceDTO(
                            clockOut: $punchTime,
                            clockOutIpAddress: 'biometric',
                            clockOutLatitude: null,
                            clockOutLongitude: null,
                            totalWork: null, // لا يمكن حساب الوقت بدون حضور
                            earlyLeaving: null,
                            overtime: null,
                            status: 'pending', // للمراجعة من HR
                        );

                        $updatedAttendance = $this->attendanceRepository->clockOut($attendance, $updateDto);

                        // إرسال تنبيه للمستوى الأعلى والشركة
                        $employeeHierarchy = $userDetails->designation?->hierarchy_level ?? 5;

                        $higherLevelUsers = \App\Models\UserDetails::where('company_id', $companyId)
                            ->whereHas('designation', function ($query) use ($employeeHierarchy) {
                                $query->where('hierarchy_level', '<', $employeeHierarchy)
                                    ->whereNotNull('hierarchy_level');
                            })
                            ->pluck('user_id')
                            ->toArray();

                        $notifyUsers = array_merge($higherLevelUsers, [$companyId]);
                        $notifyUsers = array_unique($notifyUsers);

                        $this->notificationService->sendCustomNotification(
                            moduleOption: 'attendance',
                            moduleKeyId: (string) $updatedAttendance->time_attendance_id,
                            staffIds: $notifyUsers,
                            status: 'Pending'
                        );

                        Log::info('Clock out without clock in notification sent', [
                            'attendance_id' => $updatedAttendance->time_attendance_id,
                            'notify_users' => $notifyUsers,
                        ]);

                        return [
                            'success' => true,
                            'type' => 'clock_out_without_clock_in',
                            'message' => 'تم تسجيل الانصراف - يجب مراجعة الحضور من قسم HR',
                            'warning' => 'لم يتم تسجيل حضور لهذا اليوم',
                            'data' => array_merge($baseResponseData, [
                                'attendance_id' => $updatedAttendance->time_attendance_id,
                                'needs_hr_review' => true,
                                'branch_name' => $branch?->branch_name,
                            ])
                        ];
                    }

                    if ($attendance->clock_out) {
                        throw new \Exception('تم تسجيل الانصراف لهذا اليوم بالفعل');
                    }

                    // حساب الخروج المبكر والوقت الإضافي
                    if ($officeShift) {
                        $earlyLeaving = $officeShift->calculateEarlyLeaving($punchDate, $punchTime);
                        $overtime = $officeShift->calculateOvertime($punchDate, $punchTime);
                    }

                    Log::info('Biometric clock out', array_merge($baseResponseData, [
                        'early_leaving' => $earlyLeaving,
                        'overtime' => $overtime,
                    ]));

                    $totalWork = $this->calculateTotalWorkHours(
                        $attendance->clock_in,
                        $punchTime,
                        $attendance->lunch_breakin,
                        $attendance->lunch_breakout
                    );

                    $dto = new UpdateAttendanceDTO(
                        clockOut: $punchTime,
                        clockOutIpAddress: 'biometric',
                        clockOutLatitude: null,
                        clockOutLongitude: null,
                        totalWork: $totalWork,
                        earlyLeaving: $earlyLeaving,
                        overtime: $overtime,
                    );

                    $updatedAttendance = $this->attendanceRepository->clockOut($attendance, $dto);

                    return [
                        'success' => true,
                        'type' => 'clock_out',
                        'message' => 'تم تسجيل الانصراف بنجاح',
                        'data' => array_merge($baseResponseData, [
                            'attendance_id' => $updatedAttendance->time_attendance_id,
                            'total_work' => $totalWork,
                            'early_leaving' => $earlyLeaving,
                            'overtime' => $overtime,
                            'shift_out_time' => $officeShift?->getOutTimeForDate($punchDate),
                            'branch_name' => $branch?->branch_name,
                        ])
                    ];

                case 3: // Break In (بداية استراحة الغداء)
                    // السماح ببداية الاستراحة بدون حضور (للمراجعة من HR)
                    if (!$attendance) {
                        Log::warning('Biometric break in without clock in', $baseResponseData);

                        // إنشاء سجل حضور جديد بدون clock_in للمراجعة من HR
                        $dto = new CreateAttendanceDTO(
                            companyId: $companyId,
                            branchId: $branchId,
                            employeeId: $userId,
                            attendanceDate: $punchDate,
                            clockIn: null,
                            clockInIpAddress: null,
                            clockInLatitude: null,
                            clockInLongitude: null,
                            shiftId: $userDetails->office_shift_id ?? 0,
                            workFromHome: 0,
                            timeLate: null,
                        );

                        $attendance = $this->attendanceRepository->clockIn($dto);

                        // تسجيل بداية الاستراحة مباشرة
                        $updateDto = new UpdateAttendanceDTO(
                            lunchBreakIn: $punchTime,
                            status: 'pending',
                        );

                        $updatedAttendance = $this->attendanceRepository->lunchBreakIn($attendance, $updateDto);

                        return [
                            'success' => true,
                            'type' => 'break_in_without_clock_in',
                            'message' => 'تم تسجيل بداية الاستراحة - يجب مراجعة الحضور من قسم HR',
                            'warning' => 'لم يتم تسجيل حضور لهذا اليوم',
                            'data' => array_merge($baseResponseData, [
                                'attendance_id' => $updatedAttendance->time_attendance_id,
                                'needs_hr_review' => true,
                            ])
                        ];
                    }

                    if ($attendance->lunch_breakin && !$attendance->lunch_breakout) {
                        throw new \Exception('لقد بدأت استراحة الغداء بالفعل');
                    }
                    if ($attendance->clock_out) {
                        throw new \Exception('تم تسجيل الانصراف لهذا اليوم بالفعل');
                    }

                    Log::info('Biometric lunch break start', $baseResponseData);

                    $dto = new UpdateAttendanceDTO(
                        lunchBreakIn: $punchTime,
                    );

                    $updatedAttendance = $this->attendanceRepository->lunchBreakIn($attendance, $dto);

                    return [
                        'success' => true,
                        'type' => 'break_in',
                        'message' => 'تم تسجيل بداية استراحة الغداء بنجاح',
                        'data' => array_merge($baseResponseData, [
                            'attendance_id' => $updatedAttendance->time_attendance_id,
                        ])
                    ];

                case 2: // Break Out (نهاية استراحة الغداء)
                    // السماح بنهاية الاستراحة بدون حضور (للمراجعة من HR)
                    if (!$attendance) {
                        Log::warning('Biometric break out without clock in', $baseResponseData);

                        // إنشاء سجل حضور جديد بدون clock_in للمراجعة من HR
                        $dto = new CreateAttendanceDTO(
                            companyId: $companyId,
                            branchId: $branchId,
                            employeeId: $userId,
                            attendanceDate: $punchDate,
                            clockIn: null,
                            clockInIpAddress: null,
                            clockInLatitude: null,
                            clockInLongitude: null,
                            shiftId: $userDetails->office_shift_id ?? 0,
                            workFromHome: 0,
                            timeLate: null,
                        );

                        $attendance = $this->attendanceRepository->clockIn($dto);

                        // تسجيل نهاية الاستراحة مباشرة
                        $updateDto = new UpdateAttendanceDTO(
                            lunchBreakOut: $punchTime,
                            status: 'pending',
                        );

                        $updatedAttendance = $this->attendanceRepository->lunchBreakOut($attendance, $updateDto);

                        return [
                            'success' => true,
                            'type' => 'break_out_without_clock_in',
                            'message' => 'تم تسجيل نهاية الاستراحة - يجب مراجعة الحضور من قسم HR',
                            'warning' => 'لم يتم تسجيل حضور لهذا اليوم',
                            'data' => array_merge($baseResponseData, [
                                'attendance_id' => $updatedAttendance->time_attendance_id,
                                'needs_hr_review' => true,
                            ])
                        ];
                    }

                    if (!$attendance->lunch_breakin) {
                        throw new \Exception('يجب بدء استراحة الغداء أولاً');
                    }
                    if ($attendance->lunch_breakout) {
                        throw new \Exception('لقد أنهيت استراحة الغداء بالفعل');
                    }
                    if ($attendance->clock_out) {
                        throw new \Exception('تم تسجيل الانصراف لهذا اليوم بالفعل');
                    }

                    Log::info('Biometric lunch break end', $baseResponseData);

                    $dto = new UpdateAttendanceDTO(
                        lunchBreakOut: $punchTime,
                    );

                    $updatedAttendance = $this->attendanceRepository->lunchBreakOut($attendance, $dto);

                    return [
                        'success' => true,
                        'type' => 'break_out',
                        'message' => 'تم تسجيل نهاية استراحة الغداء بنجاح',
                        'data' => array_merge($baseResponseData, [
                            'attendance_id' => $updatedAttendance->time_attendance_id,
                        ])
                    ];

                case 4: // Overtime In (حضور عمل إضافي)
                case 5: // Overtime Out (انصراف عمل إضافي)
                    // يمكن إضافة منطق العمل الإضافي هنا لاحقاً
                    Log::info('Biometric overtime punch', $baseResponseData);

                    //     return [
                    //         'success' => true,
                    //         'type' => $punchType === 4 ? 'overtime_in' : 'overtime_out',
                    //         'message' => $punchType === 4 ? 'تم تسجيل حضور العمل الإضافي' : 'تم تسجيل انصراف العمل الإضافي',
                    //         'data' => $baseResponseData
                    //     ];

                case 255: // Unspecified - سيتم تحديده تلقائياً
                default:
                    // التحديد التلقائي بناءً على حالة الحضور
                    if (!$attendance) {
                        // لا يوجد سجل = حضور
                        return $this->biometricPunch($companyId, $branchId, $employeeId, $punchTime, $verifyMode, 0, $workCode);
                    } elseif (!$attendance->clock_out) {
                        // يوجد حضور بدون انصراف = انصراف
                        return $this->biometricPunch($companyId, $branchId, $employeeId, $punchTime, $verifyMode, 1, $workCode);
                    } else {
                        throw new \Exception('تم تسجيل الحضور والانصراف لهذا اليوم بالفعل');
                    }
            }
        });
    }

    public function getAttendanceStatus(): array
    {
        return [
            'success' => true,
            'data' => [
                "status" => AttendenceStatus::toArray(),
                "attendance_type" => AttendanceStatusEnum::toArray(),
                "punch_type" => PunchTypeEnum::toArray(),
                "verify_mode" => VerifyModeEnum::toArray(),
            ]
        ];
    }
}
