<?php

namespace App\Services;

use App\DTOs\Attendance\AttendanceFilterDTO;
use App\DTOs\Attendance\CreateAttendanceDTO;
use App\DTOs\Attendance\UpdateAttendanceDTO;
use App\DTOs\Attendance\AttendanceResponseDTO;
use App\DTOs\Attendance\GetAttendanceDetailsDTO;
use App\Enums\PunchTypeEnum;
use App\Enums\VerifyModeEnum;
use App\Models\User;
use App\Repository\Interface\AttendanceRepositoryInterface;
use App\Services\SimplePermissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceService
{
    public function __construct(
        protected AttendanceRepositoryInterface $attendanceRepository,
        protected SimplePermissionService $permissionService,
        protected HolidayService $holidayService,
        protected NotificationService $notificationService,
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

        return [
            'data' => $records->items(),
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
    public function getTodayStatus(User $currentUser, ?int $targetEmployeeId = null): array
    {
        $targetId = $currentUser->user_id;

        if ($targetEmployeeId !== null && $targetEmployeeId !== $currentUser->user_id) {
            // Check permissions
            // Company admins or users with 'timesheet' can see anyone (as per user request)
            $canViewAll = $currentUser->user_type === 'company' || $this->permissionService->checkPermission($currentUser, 'timesheet');

            if (!$canViewAll) {
                throw new \Exception('ليس لديك صلاحية لعرض حالة حضور موظف آخر');
            }
            $targetId = $targetEmployeeId;
        }

        $attendance = $this->attendanceRepository->findTodayAttendance($targetId);

        if (!$attendance) {
            return [
                'has_clocked_in' => false,
                'has_clocked_out' => false,
                'on_lunch_break' => false,
                'attendance' => null,
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
     * Get monthly attendance report
     */
    /**
     * Get monthly attendance report
     */
    public function getMonthlyReport(User $currentUser, string $month, ?int $targetEmployeeId = null): array
    {
        $targetId = $currentUser->user_id;

        if ($targetEmployeeId !== null && $targetEmployeeId !== $currentUser->user_id) {
            // Check permissions
            // Company admins or users with 'timesheet' can see anyone (as per user request)
            $canViewAll = $currentUser->user_type === 'company' || $this->permissionService->checkPermission($currentUser, 'timesheet');

            if (!$canViewAll) {
                throw new \Exception('ليس لديك صلاحية لعرض تقرير حضور موظف آخر');
            }
            $targetId = $targetEmployeeId;
        }

        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($currentUser);
        return $this->attendanceRepository->getMonthlyReport($targetId, $month, $effectiveCompanyId);
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
            // 1. البحث عن الموظف باستخدام المفتاح المركب
            $userDetails = \App\Models\UserDetails::byBiometricId($companyId, $branchId, $employeeId)->first();

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

            // 1. التحقق من العطلات الرسمية
            if ($this->holidayService->isHoliday($punchDate, $companyId)) {
                $holiday = $this->holidayService->getHolidayForDate($punchDate, $companyId);
                throw new \Exception('لا يمكن تسجيل البصمة في يوم عطلة: ' . ($holiday['event_name'] ?? 'عطلة رسمية'));
            }

            // 2. التحقق من الإجازات المعتمدة
            $approvedLeave = \App\Models\LeaveApplication::forEmployee($userId)
                ->forCompany($companyId)
                ->approved()
                ->whereRaw("? BETWEEN from_date AND to_date", [$punchDate])
                ->first();

            if ($approvedLeave) {
                throw new \Exception('لا يمكن تسجيل البصمة - لديك إجازة معتمدة لهذا اليوم');
            }

            // 3. الحصول على بيانات الشيفت
            $officeShift = null;
            $timeLate = '00:00';
            $earlyLeaving = '00:00';
            $overtime = '00:00';

            // جلب بيانات الفرع للإحداثيات
            $branch = \App\Models\Branch::find($branchId);

            if ($userDetails->office_shift_id) {
                $officeShift = \App\Models\OfficeShift::find($userDetails->office_shift_id);

                if ($officeShift) {
                    // 4. التحقق من أن اليوم ليس يوم عطلة أسبوعية (شيفت)
                    if ($officeShift->isDayOff($punchDate)) {
                        throw new \Exception('لا يمكن تسجيل البصمة - هذا اليوم عطلة أسبوعية حسب جدول الدوام');
                    }

                    // حساب التأخير للحضور
                    $timeLate = $officeShift->calculateTimeLate($punchDate, $punchTime);
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

                    Log::info('Biometric clock in', array_merge($baseResponseData, ['time_late' => $timeLate]));

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
                        timeLate: $timeLate,
                    );

                    $attendance = $this->attendanceRepository->clockIn($dto);

                    return [
                        'success' => true,
                        'type' => 'clock_in',
                        'message' => 'تم تسجيل الحضور بنجاح',
                        'data' => array_merge($baseResponseData, [
                            'attendance_id' => $attendance->time_attendance_id,
                            'time_late' => $timeLate,
                            'shift_in_time' => $officeShift?->getInTimeForDate($punchDate),
                            'branch_name' => $branch?->branch_name,
                        ])
                    ];

                case 1: // Check-Out (انصراف)
                    if (!$attendance) {
                        throw new \Exception('يجب تسجيل الحضور أولاً قبل تسجيل الانصراف');
                    }
                    if ($attendance->clock_out) {
                        throw new \Exception('تم تسجيل الانصراف لهذا اليوم بالفعل');
                    }
                    if ($attendance->lunch_breakin && !$attendance->lunch_breakout) {
                        throw new \Exception('يجب تسجيل نهاية استراحة قبل تسجيل الانصراف');
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
                    if (!$attendance) {
                        throw new \Exception('يجب تسجيل الحضور أولاً');
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
                    if (!$attendance) {
                        throw new \Exception('يجب تسجيل الحضور أولاً');
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

                    return [
                        'success' => true,
                        'type' => $punchType === 4 ? 'overtime_in' : 'overtime_out',
                        'message' => $punchType === 4 ? 'تم تسجيل حضور العمل الإضافي' : 'تم تسجيل انصراف العمل الإضافي',
                        'data' => $baseResponseData
                    ];

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
}
