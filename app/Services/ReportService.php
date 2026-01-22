<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\Interface\ReportRepositoryInterface;
use App\DTOs\Report\AttendanceReportFilterDTO;
use App\Models\User;
use App\Traits\ReportHelperTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Enums\NumericalStatusEnum;
use App\Services\ReportExportService;

/**
 * خدمة التقارير الرئيسية
 * Main Report Service
 */
class ReportService
{
    use ReportHelperTrait;

    public function __construct(
        protected ReportRepositoryInterface $reportRepository,
        protected SimplePermissionService $permissionService,
        protected ReportExportService $reportExportService,

    ) {}


    // ==========================================
    // تقارير الحضور والانصراف (Attendance Reports)
    // ==========================================

    /**
     * تقرير الحضور الشهري
     */
    // Completed
    public function generateAttendanceMonthlyReport(User $user, AttendanceReportFilterDTO $filters): void
    {
        // زيادة حدود الذاكرة والوقت للتقارير الكبيرة
        ini_set('memory_limit', '1024M');
        set_time_limit(600);

        // 1. Determine Date Range (Month Start to End)
        $startDate = $filters->startDate;
        $endDate = $filters->endDate;
        if (!$startDate && $filters->month) {
            $startDate = $filters->getMonthStartDate();
            $endDate = $filters->getMonthEndDate();
        } elseif (!$startDate) {
            // Default to current month if nothing provided
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
        }

        // 2. Fetch Employees (Hierarchy Based)
        $rawEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $filters->companyId);
        $employees = collect($rawEmployees);

        if (!empty($filters->employeeIds)) {
            $employees = $employees->whereIn('user_id', $filters->employeeIds);
        } elseif ($filters->employeeId) {
            $employees = $employees->where('user_id', $filters->employeeId);
        }

        // 3. Fetch Attendance Data
        // Ensure filters covers the date range
        $filters->startDate = $startDate;
        $filters->endDate = $endDate;
        $attendanceData = $this->reportRepository->getAttendanceMonthlyReport($filters);

        // 4. Group & Merge
        $groupedAttendance = $attendanceData->groupBy('attendance_date')->map(function ($items) {
            return $items->keyBy('employee_id');
        });

        $fullData = collect();
        $period = \Carbon\CarbonPeriod::create($startDate, $endDate);

        // Pre-fetch default shifts for ALL employees from UserDetails
        $employeeIds = $employees->pluck('user_id')->toArray();
        $defaultShifts = DB::table('ci_erp_users_details')
            ->join('ci_office_shifts', 'ci_erp_users_details.office_shift_id', '=', 'ci_office_shifts.office_shift_id')
            ->whereIn('ci_erp_users_details.user_id', $employeeIds)
            ->select([
                'ci_erp_users_details.user_id',
                'ci_office_shifts.shift_name as default_shift_name',
                'ci_office_shifts.monday_in_time as default_monday_in',
                'ci_office_shifts.tuesday_in_time as default_tuesday_in',
                'ci_office_shifts.wednesday_in_time as default_wednesday_in',
                'ci_office_shifts.thursday_in_time as default_thursday_in',
                'ci_office_shifts.friday_in_time as default_friday_in',
                'ci_office_shifts.saturday_in_time as default_saturday_in',
                'ci_office_shifts.sunday_in_time as default_sunday_in',
            ])
            ->get()
            ->keyBy('user_id');

        // Build shift cache - Start with defaults, then override from attendance records
        $employeeShiftCache = [];
        foreach ($defaultShifts as $userId => $shift) {
            $employeeShiftCache[$userId] = (array)$shift;
        }

        // Override with attendance-based shift info (more specific if available)
        foreach ($attendanceData as $record) {
            $empId = $record->employee_id;
            if ($record->shift_name_joined) {
                // Use array for faster access than object overhead in this specific cache loop
                // Or just keep as array since we cast to array above
                $employeeShiftCache[$empId] = [
                    'shift_name_joined' => $record->shift_name_joined,
                    'default_shift_name' => $record->default_shift_name ?? $employeeShiftCache[$empId]['default_shift_name'] ?? null,
                    'monday_in_time' => $record->monday_in_time,
                    'tuesday_in_time' => $record->tuesday_in_time,
                    'wednesday_in_time' => $record->wednesday_in_time,
                    'thursday_in_time' => $record->thursday_in_time,
                    'friday_in_time' => $record->friday_in_time,
                    'saturday_in_time' => $record->saturday_in_time,
                    'sunday_in_time' => $record->sunday_in_time,
                    'default_monday_in' => $record->default_monday_in ?? null,
                    'default_tuesday_in' => $record->default_tuesday_in ?? null,
                    'default_wednesday_in' => $record->default_wednesday_in ?? null,
                    'default_thursday_in' => $record->default_thursday_in ?? null,
                    'default_friday_in' => $record->default_friday_in ?? null,
                    'default_saturday_in' => $record->default_saturday_in ?? null,
                    'default_sunday_in' => $record->default_sunday_in ?? null,
                ];
            }
        }

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $dayRecords = $groupedAttendance->get($dateStr);

            foreach ($employees as $employee) {
                // Use Object-style access for $employee
                $empId = $employee->user_id ?? $employee['user_id'];

                if ($dayRecords && $record = $dayRecords->get($empId)) {
                    if (!$record->employee) {
                        $record->employee = (object)$employee;
                    }
                    $fullData->push($record);
                } else {
                    // Create Empty Record with Shift Info from cache - Optimized
                    $emptyRecord = new \stdClass();
                    $emptyRecord->employee = (object)$employee;
                    $emptyRecord->attendance_date = $dateStr;
                    $emptyRecord->clock_in = null;
                    $emptyRecord->clock_out = null;
                    $emptyRecord->total_work = null;
                    $emptyRecord->attendance_status = 'Absent';

                    // Apply cached shift info
                    if (isset($employeeShiftCache[$empId])) {
                        $info = $employeeShiftCache[$empId];
                        // Direct assignment is faster than loop
                        foreach ($info as $key => $value) {
                            $emptyRecord->$key = $value;
                        }
                    }

                    $fullData->push($emptyRecord);
                }
            }
        }

        $title = 'تقرير الحضور';

        // Pass Date Range Title similar to FirstLast? "From X to Y"
        $dateRangeStr = "من: $startDate إلى: $endDate";
        $title .= " ($dateRangeStr)";

        $this->reportExportService->generateAttendancePdf($fullData, $title, $filters->companyId, 'monthly');
    }

    /**
     * تقرير أول وآخر حضور/انصراف
     */
    // Completed
    public function generateAttendanceFirstLastReport(User $user, AttendanceReportFilterDTO $filters): void
    {
        // زيادة حدود الذاكرة والوقت للتقارير الكبيرة
        ini_set('memory_limit', '1024M');
        set_time_limit(600);

        // 1. Determine Date Range
        $startDate = $filters->startDate;
        $endDate = $filters->endDate;
        if (!$startDate && $filters->month) {
            $startDate = $filters->getMonthStartDate();
            $endDate = $filters->getMonthEndDate();
        }

        // 2. Fetch Data (Hierarchy Based)
        $rawEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $filters->companyId);
        $employees = collect($rawEmployees);

        // Filter by selected Employee IDs
        if (!empty($filters->employeeIds)) {
            $employees = $employees->whereIn('user_id', $filters->employeeIds);
        } elseif ($filters->employeeId) {
            $employees = $employees->where('user_id', $filters->employeeId);
        }

        $attendanceData = $this->reportRepository->getAttendanceFirstLastReport($filters);

        // 3. Group Attendance by Date -> Employee (using arrays to save memory)
        $groupedAttendance = $attendanceData->groupBy('attendance_date')->map(function ($items) {
            return $items->keyBy('employee_id');
        });

        // 4. Build Full Dataset
        $fullData = collect();
        if ($startDate && $endDate) {
            $period = \Carbon\CarbonPeriod::create($startDate, $endDate);

            // Pre-fetch default shifts
            $employeeIds = $employees->pluck('user_id')->toArray();
            $defaultShifts = DB::table('ci_erp_users_details')
                ->join('ci_office_shifts', 'ci_erp_users_details.office_shift_id', '=', 'ci_office_shifts.office_shift_id')
                ->whereIn('ci_erp_users_details.user_id', $employeeIds)
                ->select([
                    'ci_erp_users_details.user_id',
                    'ci_office_shifts.shift_name as default_shift_name',
                    'ci_office_shifts.monday_in_time as default_monday_in',
                    'ci_office_shifts.tuesday_in_time as default_tuesday_in',
                    'ci_office_shifts.wednesday_in_time as default_wednesday_in',
                    'ci_office_shifts.thursday_in_time as default_thursday_in',
                    'ci_office_shifts.friday_in_time as default_friday_in',
                    'ci_office_shifts.saturday_in_time as default_saturday_in',
                    'ci_office_shifts.sunday_in_time as default_sunday_in',
                ])
                ->get()
                ->keyBy('user_id');

            foreach ($period as $date) {
                $dateStr = $date->format('Y-m-d');
                $dayRecords = $groupedAttendance->get($dateStr);

                foreach ($employees as $employee) {
                    $empId = $employee->user_id ?? $employee['user_id'] ?? null;

                    if ($dayRecords && $record = $dayRecords->get($empId)) {
                        $fullData->push($record);
                    } else {
                        // Empty Record - Use Array instead of stdClass to save memory overhead
                        // Using an object that mimics the structure but is lighter or just reusing structure
                        // Actually, maintaining object consistency for the PDF service is safer, 
                        // but let's just make it a simple object property assignment without overhead
                        $emptyRecord = new \stdClass();
                        $emptyRecord->employee = $employee;
                        $emptyRecord->attendance_date = $dateStr;
                        $emptyRecord->first_clock_in = null;
                        $emptyRecord->last_clock_out = null;
                        $emptyRecord->attendance_status = 'Absent';

                        // Apply default shift
                        if ($shiftInfo = $defaultShifts->get($empId)) {
                            // Direct assignment is faster than loop
                            $emptyRecord->default_shift_name = $shiftInfo->default_shift_name;
                            $emptyRecord->default_monday_in = $shiftInfo->default_monday_in;
                            $emptyRecord->default_tuesday_in = $shiftInfo->default_tuesday_in;
                            $emptyRecord->default_wednesday_in = $shiftInfo->default_wednesday_in;
                            $emptyRecord->default_thursday_in = $shiftInfo->default_thursday_in;
                            $emptyRecord->default_friday_in = $shiftInfo->default_friday_in;
                            $emptyRecord->default_saturday_in = $shiftInfo->default_saturday_in;
                            $emptyRecord->default_sunday_in = $shiftInfo->default_sunday_in;
                        }

                        $fullData->push($emptyRecord);
                    }
                }
            }
        } else {
            $fullData = $attendanceData;
        }

        $title = 'الحضور والانصراف - الأول والأخير';
        $dateRange = ($startDate && $endDate) ? 'من: ' . $startDate . ' إلى: ' . $endDate : '';

        $this->reportExportService->generateFirstLastPdf($fullData, $title, $filters->companyId, $dateRange);
    }


    /**
     * تقرير سجلات الوقت
     */
    // Completed
    public function generateAttendanceTimeRecordsReport(User $user, AttendanceReportFilterDTO $filters): void
    {
        // Validate: Must have single employee
        if (!$filters->employeeId) {
            Log::error([
                'user_id' => $user->user_id,
                'company_id' => $filters->companyId,
                'message' => 'يجب تحديد موظف واحد لهذا التقرير',
            ]);
            throw new \InvalidArgumentException('يجب تحديد موظف واحد لهذا التقرير');
        }

        // Hierarchy permission
        $rawEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $filters->companyId);
        $allowedIds = collect($rawEmployees)->pluck('user_id')->toArray();
        if (!in_array($filters->employeeId, $allowedIds)) {
            Log::error([
                'user_id' => $user->user_id,
                'employee_id' => $filters->employeeId,
                'company_id' => $filters->companyId,
                'message' => 'ليس لديك صلاحية لعرض بيانات هذا الموظف',
            ]);
            throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات هذا الموظف');
        }

        // Fetch employee info
        $employee = User::with('details.designation')
            ->find($filters->employeeId);

        $data = $this->reportRepository->getAttendanceTimeRecordsReport($filters);

        $title = 'سجلات الوقت';
        $dateRange = '';
        if ($filters->startDate && $filters->endDate) {
            $dateRange = 'من: ' . $filters->startDate . ' إلى: ' . $filters->endDate;
        }

        $this->reportExportService->generateTimeRecordsPdf($data, $title, $filters->companyId, $employee, $dateRange);
    }

    /**
     * تقرير الحضور بنطاق زمني
     */
    // Completed
    public function generateAttendanceDateRangeReport(User $user, AttendanceReportFilterDTO $filters): void
    {
        // Require single employee
        if (!$filters->employeeId) {
            Log::error([
                'user_id' => $user->user_id,
                'company_id' => $filters->companyId,
                'message' => 'يجب تحديد موظف واحد لهذا التقرير',
            ]);
            throw new \InvalidArgumentException('يجب تحديد موظف واحد لهذا التقرير');
        }

        // Hierarchy permission check
        $rawEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $filters->companyId);
        $allowedIds = collect($rawEmployees)->pluck('user_id')->toArray();
        if (!in_array($filters->employeeId, $allowedIds)) {
            Log::error([
                'user_id' => $user->user,
                'employee_id' => $filters->employeeId,
                'company_id' => $filters->companyId,
                'message' => 'ليس لديك صلاحية لعرض بيانات هذا الموظف',
            ]);
            throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات هذا الموظف');
        }

        // Fetch Employee
        $employee = User::with('details.designation')
            ->find($filters->employeeId);

        $data = $this->reportRepository->getAttendanceDateRangeReport($filters);

        $title = 'الحضور والانصراف - النطاق الزمني';
        $dateRange = '';
        if ($filters->startDate && $filters->endDate) {
            $dateRange = 'من: ' . $filters->startDate . ' إلى: ' . $filters->endDate;
        }

        $this->reportExportService->generateDateRangePdf($data, $title, $filters->companyId, $employee, $dateRange);
    }

    /**
     * تقرير سجل الدوام (Timesheet)
     */
    // Completed
    public function generateTimesheetReport(User $user, AttendanceReportFilterDTO $filters): void
    {
        $rawEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $filters->companyId);
        $allowedIds = collect($rawEmployees)->pluck('user_id')->toArray();

        // If specific employee selected, validate permission
        if ($filters->employeeId && !in_array($filters->employeeId, $allowedIds)) {
            Log::error([
                'user_id' => $user->user_id,
                'employee_id' => $filters->employeeId,
                'company_id' => $filters->companyId,
                'message' => 'ليس لديك صلاحية لعرض بيانات هذا الموظف',
            ]);
            throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات هذا الموظف');
        }

        $data = $this->reportRepository->getTimesheetReport($filters, $allowedIds);

        $title = 'تقرير ملخص الجدول الزمني';
        $dateRange = '';
        if ($filters->startDate && $filters->endDate) {
            $dateRange = 'من: ' . $filters->startDate . ' إلى: ' . $filters->endDate;
        } elseif ($filters->month) {
            $dateRange = 'شهر: ' . $filters->month;
        }

        $this->reportExportService->generateTimesheetPdf($data, $title, $filters->companyId, $dateRange);
    }

    // ==========================================
    // التقارير المالية (Financial Reports)
    // ==========================================

    /**
     * تقرير السلف والقروض
     */
    // Completed
    public function generateLoanReport(User $user, int $companyId, array $filters = []): void
    {
        // Hierarchy Check
        $rawEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId);
        $allowedIds = collect($rawEmployees)->pluck('user_id')->toArray();

        // 1. One Employee Selected
        if (!empty($filters['employee_id'])) {
            if (!in_array($filters['employee_id'], $allowedIds)) {
                Log::error([
                    'user_id' => $user->user_id,
                    'employee_id' => $filters['employee_id'],
                    'company_id' => $companyId,
                    'message' => 'ليس لديك صلاحية لعرض بيانات هذا الموظف',
                ]);
                throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات هذا الموظف');
            }
        }
        // 2. Group of Employees Selected (staffs array mapped to employeeIds in DTO)
        elseif (!empty($filters['employeeIds'])) {
            // Validate all selected IDs are allowed
            $requestedIds = (array) $filters['employeeIds'];
            $validIds = array_intersect($requestedIds, $allowedIds);

            if (empty($validIds)) {
                Log::error([
                    'user_id' => $user->user_id,
                    'employee_ids' => $filters['employeeIds'],
                    'company_id' => $companyId,
                    'message' => 'ليس لديك صلاحية لعرض بيانات الموظفين المحددين',
                ]);
                throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات الموظفين المحددين');
            }
            // Use only valid intersecting IDs
            $filters['employee_ids'] = $validIds;
        }
        // 3. "All" Selected (or nothing selected) - default to all allowed
        else {
            if (empty($allowedIds)) {
                Log::error([
                    'user_id' => $user->user_id,
                    'company_id' => $companyId,
                    'message' => 'ليس لديك صلاحية لعرض بيانات أي موظف',
                ]);
                throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات أي موظف');
            }
            $filters['employee_ids'] = $allowedIds;
        }

        $data = $this->reportRepository->getLoanReport($companyId, $filters);
        $title = 'كشف سلف الموظفين';

        $this->reportExportService->generateLoanPdf($data, $title, $companyId, $filters);
    }

    /**
     * تقرير الرواتب الشهري
     */
    // Completed
    public function generatePayrollReport(User $user, int $companyId, array $filters = []): void
    {
        // Hierarchy Check
        $rawEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId);
        $allowedIds = collect($rawEmployees)->pluck('user_id')->toArray();

        // 1. One Employee Selected
        if (!empty($filters['employee_id'])) {
            if (!in_array($filters['employee_id'], $allowedIds)) {
                Log::error([
                    'user_id' => $user->user_id,
                    'employee_id' => $filters['employee_id'],
                    'company_id' => $companyId,
                    'message' => 'ليس لديك صلاحية لعرض بيانات هذا الموظف',
                ]);
                throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات هذا الموظف');
            }
        }
        // 2. "All" Selected (or nothing selected) - default to all allowed
        else {
            if (empty($allowedIds)) {
                Log::error([
                    'user_id' => $user->user_id,
                    'company_id' => $companyId,
                    'message' => 'ليس لديك صلاحية لعرض بيانات أي موظف',
                ]);
                throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات أي موظف');
            }
            // IMPORTANT: Pass allowed IDs to filters to restrict the repository query
            $filters['employee_ids'] = $allowedIds;
        }

        // جلب البيانات
        $payslips = $this->reportRepository->getPayrollReport($companyId, $filters);

        if ($payslips->isEmpty()) {
            throw new \InvalidArgumentException('لا توجد بيانات رواتب ');
        }

        $paymentDate = $filters['payment_date'] ?? date('Y-m');
        $title = 'كشف المرتبات الشهرية ' . $paymentDate;

        $this->reportExportService->generatePayrollPdf($payslips, $title, $companyId, $filters);
    }

    /**
     * تقرير الجوائز (Awards)
     */
    // Completed
    public function generateAwardsReport(User $user, int $companyId, array $filters = []): void
    {
        // Hierarchy Check
        $rawEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId);
        $allowedIds = collect($rawEmployees)->pluck('user_id')->toArray();

        // 1. One Employee Selected
        if (!empty($filters['employee_id'])) {
            if (!in_array($filters['employee_id'], $allowedIds)) {
                Log::error([
                    'user_id' => $user->user_id,
                    'employee_id' => $filters['employee_id'],
                    'company_id' => $companyId,
                    'message' => 'ليس لديك صلاحية لعرض بيانات هذا الموظف',
                ]);
                throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات هذا الموظف');
            }
        }
        // 2. "All" Selected (or nothing selected) - default to all allowed
        else {
            if (empty($allowedIds)) {
                Log::error([
                    'user_id' => $user->user_id,
                    'company_id' => $companyId,
                    'message' => 'ليس لديك صلاحية لعرض بيانات أي موظف',
                ]);
                throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات أي موظف');
            }
            // IMPORTANT: Pass allowed IDs to filters to restrict the repository query
            $filters['employee_ids'] = $allowedIds;
        }

        // جلب البيانات
        $awards = $this->reportRepository->getAwardsReport($companyId, $filters);

        if ($awards->isEmpty()) {
            throw new \InvalidArgumentException('لا توجد جوائز');
        }

        $dateRange = '';
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $dateRange = 'من: ' . $filters['start_date'] . ' إلى: ' . $filters['end_date'];
        }

        $title = 'تقرير المكافآت';
        $this->reportExportService->generateAwardsPdf($awards, $title, $companyId, $dateRange, $user);
    }

    // ==========================================
    // تقارير الموارد البشرية (HR Reports)
    // ==========================================

    /**
     * تقرير الإجازات
     */
    // Completed
    public function generateLeaveReport(User $user, int $companyId, array $filters = []): void
    {
        // add hierercally permission check
        $rawEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId);
        $allowedIds = collect($rawEmployees)->pluck('user_id')->toArray();
        if (!empty($filters['employeeIds'])) {
            $allowedIds = array_intersect($allowedIds, $filters['employeeIds']);
        }
        if (empty($allowedIds)) {
            Log::error([
                'user_id' => $user->user_id,
                'employee_id' => $filters['employeeIds'] ?? null,
                'company_id' => $companyId,
                'message' => 'ليس لديك صلاحية لعرض بيانات هذا الموظف',
            ]);
            throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات هذا الموظف');
        }

        // Apply allowed IDs to filter
        $filters['employee_ids'] = $allowedIds; // Repository likely uses 'employee_ids' or 'employeeIds'?? Check Repo.

        $data = $this->reportRepository->getLeaveReport($companyId, $filters);

        if ($data->isEmpty()) {
            throw new \InvalidArgumentException('لا توجد إجازات');
        }
        $this->reportExportService->generateLeavePdf($data, $companyId, $filters);
    }



    /**
     * تقرير الترقيات - Placeholder
     */
    // Completed
    public function generatePromotionsReport(User $user, int $companyId, array $filters = []): void
    {
        // Hierarchy Check
        $rawEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId);
        $allowedIds = collect($rawEmployees)->pluck('user_id')->toArray();

        // 1. One Employee Selected
        if (!empty($filters['employee_id'])) {
            // Manual Existence Check (since removed from Request)
            $employeeExists = \App\Models\User::where('user_id', $filters['employee_id'])->exists();
            if (!$employeeExists) {
                throw new \InvalidArgumentException('الموظف غير موجود');
            }

            if (!in_array($filters['employee_id'], $allowedIds)) {
                Log::error([
                    'user_id' => $user->user_id,
                    'employee_id' => $filters['employee_id'],
                    'company_id' => $companyId,
                    'message' => 'ليس لديك صلاحية لعرض بيانات هذا الموظف',
                ]);
                throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات هذا الموظف');
            }
        }
        // 2. "All" Selected
        else {
            if (empty($allowedIds)) {
                Log::error([
                    'user_id' => $user->user_id,
                    'company_id' => $companyId,
                    'message' => 'ليس لديك صلاحية لعرض بيانات أي موظف',
                ]);
                throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات أي موظف');
            }
            $filters['employee_ids'] = $allowedIds;
        }

        // جلب البيانات
        $data = $this->reportRepository->getPromotionsReport($companyId, $filters);

        if ($data->isEmpty()) {
            throw new \InvalidArgumentException('لا توجد ترقيات');
        }

        $dateRange = '';
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $dateRange = 'من: ' . $filters['start_date'] . ' إلى: ' . $filters['end_date'];
        }

        $title = 'تقرير الترقيات';

        $this->reportExportService->generatePromotionsPdf($data, $title, $companyId, $dateRange, $user);
    }

    /**
     * توليد PDF للاستقالات
     */
    /**
     * تقرير الاستقالات
     */
    // Completed
    public function generateResignationsReport(User $user, int $companyId, array $filters = []): void
    {
        // Hierarchy Check
        $rawEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId);
        $allowedIds = collect($rawEmployees)->pluck('user_id')->toArray();

        // 1. One Employee Selected
        if (!empty($filters['employee_id'])) {
            // Manual Existence Check
            $employeeExists = \App\Models\User::where('user_id', $filters['employee_id'])->exists();
            if (!$employeeExists) {
                throw new \InvalidArgumentException('الموظف غير موجود');
            }

            if (!in_array($filters['employee_id'], $allowedIds)) {
                Log::error([
                    'user_id' => $user->user_id,
                    'employee_id' => $filters['employee_id'],
                    'company_id' => $companyId,
                    'message' => 'ليس لديك صلاحية لعرض بيانات هذا الموظف',
                ]);
                throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات هذا الموظف');
            }
        }
        // 2. "All" Selected
        else {
            if (empty($allowedIds)) {
                Log::error([
                    'user_id' => $user->user_id,
                    'company_id' => $companyId,
                    'message' => 'ليس لديك صلاحية لعرض بيانات أي موظف',
                ]);
                throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات أي موظف');
            }
            $filters['employee_ids'] = $allowedIds;
        }

        $data = $this->reportRepository->getResignationsReport($companyId, $filters);

        if ($data->isEmpty()) {
            throw new \InvalidArgumentException('لا توجد استقالات');
        }

        $title = 'تقرير الاستقالات';
        $dateRange = '';
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $dateRange = 'من: ' . $filters['start_date'] . ' إلى: ' . $filters['end_date'];
        }

        // Show "الكل" if no status filter, otherwise use enum label
        if (array_key_exists('status', $filters) && $filters['status'] !== null && $filters['status'] !== '') {
            $statusEnum = NumericalStatusEnum::tryFrom((int)$filters['status']);
            $statusText = 'الحالة: ' . ($statusEnum?->labelAr() ?? 'الكل');
        } else {
            $statusText = 'الحالة: الكل';
        }

        $this->reportExportService->generateResignationsPdf($data, $title, $companyId, $dateRange, $statusText);
    }


    /**
     * تقرير إنهاء الخدمة
     */
    // Completed
    public function generateTerminationsReport(User $user, int $companyId, array $filters = []): void
    {
        // Hierarchy Check
        $rawEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId);
        $allowedIds = collect($rawEmployees)->pluck('user_id')->toArray();

        // Ensure user can only see allowed employees (Hierarchy enforcement)
        if (empty($allowedIds)) {
            // Fallback if no subordinates found (should usually contain at least self if applicable, or empty for high level)
            // Logic: if allowedIds is empty, user sees nothing or error?
            // Assuming strict hierarchy: if empty, error.
            Log::error([
                'user_id' => $user->user_id,
                'company_id' => $companyId,
                'message' => 'ليس لديك صلاحية لعرض بيانات أي موظف',
            ]);
            throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات أي موظف');
        }
        $filters['employee_ids'] = $allowedIds;

        // Manual existence check for employee_id skipped as filter is removed from usage/UI.
        // But if someone manually passes it via API, repository logic will check if it's in employee_ids via separate filter?
        // Current repo implementation:
        // if (!empty($filters['employee_ids'])) { $query->whereIn('employee_id', $filters['employee_ids']); }
        // So even if we don't pass 'employee_id' explicitly, 'employee_ids' enforces security.

        $data = $this->reportRepository->getTerminationsReport($companyId, $filters);

        if ($data->isEmpty()) {
            throw new \InvalidArgumentException('لا توجد بيانات إنهاء خدمة للفترة المحددة');
        }

        $title = 'تقرير إنهاء الخدمة';
        $dateRange = '';
        $statusText = '';
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $dateRange = 'من: ' . $filters['start_date'] . ' إلى: ' . $filters['end_date'];
        }

        // Show "الكل" if no status filter, otherwise use enum label
        if (array_key_exists('status', $filters) && $filters['status'] !== null && $filters['status'] !== '') {
            $statusEnum = NumericalStatusEnum::tryFrom((int)$filters['status']);
            $statusText = 'الحالة: ' . ($statusEnum?->labelAr() ?? 'الكل');
        } else {
            $statusText = 'الحالة: الكل';
        }

        $this->reportExportService->generateTerminationsPdf($data, $title, $companyId, $dateRange, $statusText);
    }


    /**
     * تقرير التحويلات
     */
    // Completed
    public function generateTransfersReport(User $user, int $companyId, array $filters = []): void
    {
        // Hierarchy Check - Only for staff users, company users see all transfers
        if ($user->user_type === 'staff') {
            $rawEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId);
            $allowedIds = collect($rawEmployees)->pluck('user_id')->toArray();

            if (empty($allowedIds)) {
                throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات أي موظف');
            }
            $filters['employee_ids'] = $allowedIds;
        }
        // Company users see all transfers - no employee_ids filter

        $data = $this->reportRepository->getTransfersReport($companyId, $filters);

        if ($data->isEmpty()) {
            throw new \InvalidArgumentException('لا توجد تحويلات للفترة المحددة');
        }

        $title = 'تقرير التحويلات';
        $dateRange = '';
        $statusText = '';
        $transferTypeText = '';

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $dateRange = 'من: ' . $filters['start_date'] . ' إلى: ' . $filters['end_date'];
        }

        // Status filter text
        if (array_key_exists('status', $filters) && $filters['status'] !== null && $filters['status'] !== '') {
            $statusEnum = NumericalStatusEnum::tryFrom((int)$filters['status']);
            $statusText = 'الحالة: ' . ($statusEnum?->labelAr() ?? 'الكل');
        } else {
            $statusText = 'الحالة: الكل';
        }

        // Transfer type filter text
        $transferType = $filters['transfer_type'] ?? 'all';
        $transferTypeText = match ($transferType) {
            'internal' => 'نوع التحويل: نقل داخلي',
            'branch' => 'نوع التحويل: نقل بين الفروع',
            'intercompany' => 'نوع التحويل: نقل بين الشركات',
            default => 'نوع التحويل: الكل',
        };

        $this->reportExportService->generateTransfersPdf($data, $title, $companyId, $dateRange, $statusText, $transferTypeText, $transferType);
    }

    // ==========================================
    // تقارير الوثائق (Document Reports)
    // ==========================================

    /**
     * تقرير تجديد الإقامة
     */
    // Completed
    public function generateResidenceRenewalReport(User $user, int $companyId, array $filters = []): void
    {
        // Hierarchy Check - Only for staff users
        if ($user->user_type === 'staff') {
            $rawEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId);
            $allowedIds = collect($rawEmployees)->pluck('user_id')->toArray();

            if (empty($allowedIds)) {
                throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات أي موظف');
            }
            $filters['employee_ids'] = $allowedIds;
        }

        $data = $this->reportRepository->getResidenceRenewalReport($companyId, $filters);

        if ($data->isEmpty()) {
            throw new \InvalidArgumentException('لا توجد بيانات تجديد إقامة');
        }

        $title = 'تقرير تجديد الإقامة';

        $this->reportExportService->generateResidenceRenewalPdf($data, $title, $companyId, $filters);
    }

    /**
     * تقرير العقود قريبة الانتهاء
     */
    public function generateExpiringContractsReport(User $user, int $companyId, array $filters = []): void
    {
        // Hierarchy Check
        if ($user->user_type === 'staff') {
            $rawEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId);
            $allowedIds = collect($rawEmployees)->pluck('user_id')->toArray();

            if (empty($allowedIds)) {
                throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات أي موظف');
            }
            $filters['employee_ids'] = $allowedIds;
        }

        $data = $this->reportRepository->getExpiringContractsReport($companyId, $filters);

        if ($data->isEmpty()) {
            throw new \InvalidArgumentException('لا توجد عقود منتهية أو قريبة الانتهاء في الفترة المحددة');
        }

        $title = 'تقرير انتهاء العقود';

        $this->reportExportService->generateExpiringContractsPdf($data, $title, $companyId, $filters);
    }


    /**
     * تقرير الهويات/الإقامات قريبة الانتهاء
     */
    public function generateExpiringDocumentsReport(User $user, int $companyId, array $filters = []): void
    {
        // Hierarchy Check
        if ($user->user_type === 'staff') {
            $rawEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId);
            $allowedIds = collect($rawEmployees)->pluck('user_id')->toArray();

            if (empty($allowedIds)) {
                throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات أي موظف');
            }
            $filters['employee_ids'] = $allowedIds;
        }

        $data = $this->reportRepository->getExpiringDocumentsReport($companyId, $filters);

        if ($data->isEmpty()) {
            throw new \InvalidArgumentException('لا توجد وثائق منتهية أو قريبة الانتهاء في الفترة المحددة');
        }

        $title = 'تقرير الهويات/الإقامات قريبة الانتهاء';

        $this->reportExportService->generateExpiringDocumentsPdf($data, $title, $companyId, $filters);
    }




    // ==========================================
    // تقارير الموظفين (Employee Reports)
    // ==========================================

    /**
     * تقرير الموظفين حسب الفرع
     */
    public function generateEmployeesByBranchReport(User $user, int $companyId, array $filters = []): void
    {
        // Hierarchy Check
        if ($user->user_type === 'staff') {
            $rawEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId);
            $allowedIds = collect($rawEmployees)->pluck('user_id')->toArray();

            if (empty($allowedIds)) {
                throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات أي موظف');
            }
            $filters['employee_ids'] = $allowedIds;
        }

        $data = $this->reportRepository->getEmployeesByBranchReport($companyId, $filters);

        if ($data->isEmpty()) {
            throw new \InvalidArgumentException('لا توجد بيانات لهذا التقرير');
        }

        $title = 'تقرير الموظفين حسب الفرع';

        $this->reportExportService->generateEmployeesByBranchPdf($data, $title, $companyId, $filters);
    }

    /**
     * تقرير الموظفين حسب الدولة
     */
    public function generateEmployeesByCountryReport(User $user, int $companyId, array $filters = []): void
    {
        // Hierarchy Check
        if ($user->user_type === 'staff') {
            $rawEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId);
            $allowedIds = collect($rawEmployees)->pluck('user_id')->toArray();

            if (empty($allowedIds)) {
                throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات أي موظف');
            }
            $filters['employee_ids'] = $allowedIds;
        }

        $data = $this->reportRepository->getEmployeesByCountryReport($companyId, $filters);

        if ($data->isEmpty()) {
            throw new \InvalidArgumentException('لا توجد بيانات لهذا التقرير');
        }

        $title = 'تقرير الموظفين حسب الدولة';
        $this->reportExportService->generateEmployeesByCountryPdf($data, $title, $companyId, $filters);
    }


    /**
     * تقرير نهاية الخدمة (End of Service Report)
     */
    public function endOfService(User $user, int $companyId, array $filters): void
    {
        // Hierarchy Check
        $rawEmployees = $this->permissionService->getEmployeesByHierarchy($user->user_id, $companyId);
        $allowedIds = collect($rawEmployees)->pluck('user_id')->toArray();

        // 1. One Employee Selected
        if (!empty($filters['employee_id'])) {
            // Manual Existence Check
            $employeeExists = \App\Models\User::where('user_id', $filters['employee_id'])->exists();
            if (!$employeeExists) {
                throw new \InvalidArgumentException('الموظف غير موجود');
            }

            if (!in_array($filters['employee_id'], $allowedIds)) {
                throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات هذا الموظف');
            }
        }
        // 2. "All" Selected - use employee_ids from request or allowedIds
        else {
            if (empty($allowedIds)) {
                throw new \InvalidArgumentException('ليس لديك صلاحية لعرض بيانات أي موظف');
            }
            // If employee_ids provided, intersect with allowed
            if (!empty($filters['employee_ids'])) {
                $filters['employee_ids'] = array_intersect($filters['employee_ids'], $allowedIds);
            } else {
                $filters['employee_ids'] = $allowedIds;
            }
        }

        // Ensure company isolation
        $filters['company_id'] = $companyId;

        $data = $this->reportRepository->getEndOfServiceReport($filters);

        if ($data->isEmpty()) {
            throw new \InvalidArgumentException('لا توجد بيانات نهاية خدمة');
        }

        $title = 'تقرير مكافآت نهاية الخدمة';

        $this->reportExportService->generateEndOfServicePdf($data, $title, $companyId, $filters);
    }
}
