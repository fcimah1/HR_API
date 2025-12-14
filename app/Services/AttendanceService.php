<?php

namespace App\Services;

use App\DTOs\Attendance\AttendanceFilterDTO;
use App\DTOs\Attendance\CreateAttendanceDTO;
use App\DTOs\Attendance\UpdateAttendanceDTO;
use App\DTOs\Attendance\AttendanceResponseDTO;
use App\DTOs\Attendance\GetAttendanceDetailsDTO;
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
    public function lunchBreakIn(int $userId): array
    {
        return DB::transaction(function () use ($userId) {
            $attendance = $this->attendanceRepository->findTodayAttendance($userId);

            if (!$attendance) {
                throw new \Exception('يجب تسجيل الحضور أولاً');
            }

            if ($attendance->lunch_breakin && !$attendance->lunch_breakout) {
                throw new \Exception('لقد بدأت استراحة الغداء بالفعل');
            }

            $updatedAttendance = $this->attendanceRepository->lunchBreakIn($attendance);

            return AttendanceResponseDTO::fromModel($updatedAttendance)->toArray();
        });
    }

    /**
     * End lunch break
     */
    public function lunchBreakOut(int $userId): array
    {
        return DB::transaction(function () use ($userId) {
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

            $updatedAttendance = $this->attendanceRepository->lunchBreakOut($attendance);

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
     * @return array
     */
    public function biometricPunch(int $companyId, int $branchId, string $employeeId, string $punchTime): array
    {
        return DB::transaction(function () use ($companyId, $branchId, $employeeId, $punchTime) {
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
            $punchTimeOnly = date('H:i:s', strtotime($punchTime));

            // 2. البحث عن سجل الحضور لهذا اليوم
            $attendance = $this->attendanceRepository->findTodayAttendance($userId, $punchDate);

            // 3. تحديد نوع البصمة
            if (!$attendance) {
                // لا يوجد سجل = حضور
                Log::info('Biometric clock in', [
                    'user_id' => $userId,
                    'punch_time' => $punchTime,
                ]);

                $dto = new CreateAttendanceDTO(
                    companyId: $companyId,
                    employeeId: $userId,
                    attendanceDate: $punchDate,
                    clockIn: $punchTime,
                    clockInIpAddress: 'biometric',
                    clockInLatitude: null,
                    clockInLongitude: null,
                    shiftId: $userDetails->office_shift_id ?? 0,
                    workFromHome: 0,
                );

                $attendance = $this->attendanceRepository->clockIn($dto);

                return [
                    'success' => true,
                    'type' => 'clock_in',
                    'message' => 'تم تسجيل الحضور بنجاح',
                    'data' => [
                        'user_id' => $userId,
                        'employee_id' => $employeeId,
                        'punch_time' => $punchTime,
                        'attendance_id' => $attendance->time_attendance_id,
                    ]
                ];
            }

            // يوجد سجل حضور
            if ($attendance->clock_out) {
                // الموظف سجل الحضور والانصراف بالفعل
                Log::warning('Biometric punch - Already clocked out', [
                    'user_id' => $userId,
                    'punch_time' => $punchTime,
                ]);
                throw new \Exception('تم تسجيل الحضور والانصراف لهذا اليوم بالفعل');
            }

            // 4. تسجيل الانصراف
            Log::info('Biometric clock out', [
                'user_id' => $userId,
                'punch_time' => $punchTime,
            ]);

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
            );

            $updatedAttendance = $this->attendanceRepository->clockOut($attendance, $dto);

            return [
                'success' => true,
                'type' => 'clock_out',
                'message' => 'تم تسجيل الانصراف بنجاح',
                'data' => [
                    'user_id' => $userId,
                    'employee_id' => $employeeId,
                    'punch_time' => $punchTime,
                    'attendance_id' => $updatedAttendance->time_attendance_id,
                    'total_work' => $totalWork,
                ]
            ];
        });
    }
}
