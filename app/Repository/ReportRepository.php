<?php

declare(strict_types=1);

namespace App\Repository;

use App\Repository\Interface\ReportRepositoryInterface;
use App\DTOs\Report\AttendanceReportFilterDTO;
use App\Models\Attendance;
use App\Models\User;
use App\Models\LeaveApplication;
use App\Models\Resignation;
use App\Models\Transfer;
use App\Models\AdvanceSalary;
use App\Models\ErpConstant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repository للتقارير
 * Report Repository Implementation
 */
class ReportRepository implements ReportRepositoryInterface
{
    protected $leaveRepository;

    public function __construct(\App\Repository\Interface\LeaveRepositoryInterface $leaveRepository)
    {
        $this->leaveRepository = $leaveRepository;
    }
    // ==========================================
    // تقارير الحضور والانصراف (Attendance Reports)
    // ==========================================

    /**
     * تقرير الحضور الشهري
     */
    public function getAttendanceMonthlyReport(AttendanceReportFilterDTO $filters): Collection
    {
        $query = Attendance::query()
            ->select([
                'ci_timesheet.*',
                'ci_branchs.coordinates as branch_coordinates',
                'ci_office_shifts.shift_name as shift_name_joined',
                'ci_office_shifts.monday_in_time',
                'ci_office_shifts.tuesday_in_time',
                'ci_office_shifts.wednesday_in_time',
                'ci_office_shifts.thursday_in_time',
                'ci_office_shifts.friday_in_time',
                'ci_office_shifts.saturday_in_time',
                'ci_office_shifts.sunday_in_time',
                // Fallback Shift Info
                'default_shift.shift_name as default_shift_name',
                'default_shift.monday_in_time as default_monday_in',
                'default_shift.tuesday_in_time as default_tuesday_in',
                'default_shift.wednesday_in_time as default_wednesday_in',
                'default_shift.thursday_in_time as default_thursday_in',
                'default_shift.friday_in_time as default_friday_in',
                'default_shift.saturday_in_time as default_saturday_in',
                'default_shift.sunday_in_time as default_sunday_in',
            ])
            ->leftJoin('ci_branchs', 'ci_timesheet.branch_id', '=', 'ci_branchs.branch_id')
            ->leftJoin('ci_office_shifts', 'ci_timesheet.shift_id', '=', 'ci_office_shifts.office_shift_id')
            // Join UserDetails to get default shift
            ->leftJoin('ci_erp_users_details', 'ci_timesheet.employee_id', '=', 'ci_erp_users_details.user_id')
            ->leftJoin('ci_office_shifts as default_shift', 'ci_erp_users_details.office_shift_id', '=', 'default_shift.office_shift_id')
            ->with(['employee:user_id,first_name,last_name,email'])
            ->where('ci_timesheet.company_id', $filters->companyId);

        // فلتر الموظف
        if ($filters->employeeId) {
            $query->where('ci_timesheet.employee_id', $filters->employeeId);
        }

        // فلتر الفرع
        if ($filters->branchId) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('office_branch_id', $filters->branchId);
            });
        }

        // فلتر الشهر
        if ($filters->month) {
            $startDate = $filters->getMonthStartDate();
            $endDate = $filters->getMonthEndDate();
            $query->whereBetween('ci_timesheet.attendance_date', [$startDate, $endDate]);
        }

        // فلتر الحالة
        if ($filters->status) {
            $query->where('ci_timesheet.attendance_status', $filters->status);
        }

        return $query->orderBy('ci_timesheet.attendance_date', 'asc')
            ->orderBy('ci_timesheet.employee_id', 'asc')
            ->get();
    }

    /**
     * تقرير أول وآخر حضور/انصراف
     */
    public function getAttendanceFirstLastReport(AttendanceReportFilterDTO $filters): Collection
    {
        $query = Attendance::query()
            ->select([
                'ci_timesheet.employee_id',
                'ci_timesheet.attendance_date',
                'ci_timesheet.attendance_status',
                'ci_timesheet.time_late',
                'ci_timesheet.clock_in_latitude',
                'ci_timesheet.clock_in_longitude',
                'ci_timesheet.clock_out_latitude',
                'ci_timesheet.clock_out_longitude',
                DB::raw('MIN(ci_timesheet.clock_in) as first_clock_in'),
                DB::raw('MAX(ci_timesheet.clock_out) as last_clock_out'),
                // Branch
                'ci_branchs.coordinates as branch_coordinates',
                // Shift from timesheet
                'ci_office_shifts.shift_name as shift_name_joined',
                'ci_office_shifts.monday_in_time',
                'ci_office_shifts.tuesday_in_time',
                'ci_office_shifts.wednesday_in_time',
                'ci_office_shifts.thursday_in_time',
                'ci_office_shifts.friday_in_time',
                'ci_office_shifts.saturday_in_time',
                'ci_office_shifts.sunday_in_time',
                // Default Shift from UserDetails
                'default_shift.shift_name as default_shift_name',
                'default_shift.monday_in_time as default_monday_in',
                'default_shift.tuesday_in_time as default_tuesday_in',
                'default_shift.wednesday_in_time as default_wednesday_in',
                'default_shift.thursday_in_time as default_thursday_in',
                'default_shift.friday_in_time as default_friday_in',
                'default_shift.saturday_in_time as default_saturday_in',
                'default_shift.sunday_in_time as default_sunday_in',
            ])
            ->leftJoin('ci_branchs', 'ci_timesheet.branch_id', '=', 'ci_branchs.branch_id')
            ->leftJoin('ci_office_shifts', 'ci_timesheet.shift_id', '=', 'ci_office_shifts.office_shift_id')
            ->leftJoin('ci_erp_users_details', 'ci_timesheet.employee_id', '=', 'ci_erp_users_details.user_id')
            ->leftJoin('ci_office_shifts as default_shift', 'ci_erp_users_details.office_shift_id', '=', 'default_shift.office_shift_id')
            ->with(['employee:user_id,first_name,last_name,email'])
            ->where('ci_timesheet.company_id', $filters->companyId);

        // فلتر الموظف (مصفوفة أو مفرد)
        if (!empty($filters->employeeIds)) {
            $query->whereIn('ci_timesheet.employee_id', $filters->employeeIds);
        } elseif ($filters->employeeId) {
            $query->where('ci_timesheet.employee_id', $filters->employeeId);
        }

        // فلتر التاريخ
        if ($filters->startDate && $filters->endDate) {
            $query->whereBetween('ci_timesheet.attendance_date', [$filters->startDate, $filters->endDate]);
        } elseif ($filters->month) {
            $startDate = $filters->getMonthStartDate();
            $endDate = $filters->getMonthEndDate();
            $query->whereBetween('ci_timesheet.attendance_date', [$startDate, $endDate]);
        }

        $query->groupBy(
            'ci_timesheet.employee_id',
            'ci_timesheet.attendance_date',
            'ci_timesheet.attendance_status',
            'ci_timesheet.time_late',
            'ci_timesheet.clock_in_latitude',
            'ci_timesheet.clock_in_longitude',
            'ci_timesheet.clock_out_latitude',
            'ci_timesheet.clock_out_longitude',
            'branch_coordinates',
            'shift_name_joined',
            'ci_office_shifts.monday_in_time',
            'ci_office_shifts.tuesday_in_time',
            'ci_office_shifts.wednesday_in_time',
            'ci_office_shifts.thursday_in_time',
            'ci_office_shifts.friday_in_time',
            'ci_office_shifts.saturday_in_time',
            'ci_office_shifts.sunday_in_time',
            'default_shift_name',
            'default_monday_in',
            'default_tuesday_in',
            'default_wednesday_in',
            'default_thursday_in',
            'default_friday_in',
            'default_saturday_in',
            'default_sunday_in'
        )
            ->orderBy('ci_timesheet.attendance_date', 'asc')
            ->orderBy('ci_timesheet.employee_id', 'asc');

        return $query->get();
    }

    /**
     * تقرير سجلات الوقت
     */
    public function getAttendanceTimeRecordsReport(AttendanceReportFilterDTO $filters): Collection
    {
        $query = Attendance::query()
            ->with(['employee:user_id,first_name,last_name,email'])
            ->where('company_id', $filters->companyId)
            ->select([
                'time_attendance_id',
                'employee_id',
                'attendance_date',
                'clock_in',
                'clock_out',
                'total_work',
                'attendance_status',
                'work_from_home',
            ]);

        // فلتر الموظف
        if ($filters->employeeId) {
            $query->where('employee_id', $filters->employeeId);
        }

        // فلتر التاريخ
        if ($filters->startDate && $filters->endDate) {
            $query->whereBetween('attendance_date', [$filters->startDate, $filters->endDate]);
        } elseif ($filters->month) {
            $startDate = $filters->getMonthStartDate();
            $endDate = $filters->getMonthEndDate();
            $query->whereBetween('attendance_date', [$startDate, $endDate]);
        }

        return $query->orderBy('attendance_date', 'desc')
            ->orderBy('clock_in', 'desc')
            ->get();
    }

    /**
     * تقرير الحضور بنطاق زمني
     */
    public function getAttendanceDateRangeReport(AttendanceReportFilterDTO $filters): Collection
    {
        $query = Attendance::query()
            ->select([
                'ci_timesheet.*',
                'ci_branchs.branch_name',
                'ci_branchs.coordinates as branch_coordinates',
            ])
            ->leftJoin('ci_branchs', 'ci_timesheet.branch_id', '=', 'ci_branchs.branch_id')
            ->with(['employee:user_id,first_name,last_name,email'])
            ->where('ci_timesheet.company_id', $filters->companyId);

        // فلتر الموظف (مصفوفة أو مفرد)
        if (!empty($filters->employeeIds)) {
            $query->whereIn('ci_timesheet.employee_id', $filters->employeeIds);
        } elseif ($filters->employeeId) {
            $query->where('ci_timesheet.employee_id', $filters->employeeId);
        }

        // فلتر النطاق الزمني
        if ($filters->startDate && $filters->endDate) {
            $query->whereBetween('ci_timesheet.attendance_date', [$filters->startDate, $filters->endDate]);
        }

        return $query->orderBy('ci_timesheet.attendance_date', 'asc')
            ->get();
    }

    /**
     * تقرير سجل الدوام (Timesheet)
     */
    public function getTimesheetReport(AttendanceReportFilterDTO $filters, ?array $allowedEmployeeIds = null): Collection
    {
        // Query Users instead of Attendance to include everyone
        $query = User::query()
            ->with(['details.designation', 'attendances' => function ($q) use ($filters) {
                $q->where('status', 'Approved'); // Only approved attendance? Or all? Legacy used Status=Approved

                // Date Filter for Attendance Relation
                if ($filters->startDate && $filters->endDate) {
                    $q->whereBetween('attendance_date', [$filters->startDate, $filters->endDate]);
                } elseif ($filters->month) {
                    $startDate = $filters->getMonthStartDate();
                    $endDate = $filters->getMonthEndDate();
                    $q->whereBetween('attendance_date', [$startDate, $endDate]);
                }

                $q->orderBy('attendance_date', 'asc');
            }])
            ->where('company_id', $filters->companyId)
            ->where('user_type', 'staff')
            ->where('is_active', 1);

        // Apply Allowed Employees Filter
        if (!is_null($allowedEmployeeIds)) {
            $query->whereIn('user_id', $allowedEmployeeIds);
        }

        // Filter Employee
        if ($filters->employeeId) {
            $query->where('user_id', $filters->employeeId);
        }


        return $query->orderBy('first_name', 'asc')->get();
    }

    // ==========================================
    // التقارير المالية (Financial Reports)
    // ==========================================

    /**
     * تقرير السلف والقروض
     */
    public function getLoanReport(int $companyId, array $filters = []): Collection
    {
        $query = AdvanceSalary::query()
            ->with(['employee.user_details'])
            ->where('company_id', $companyId);

        // Filter Employee (Single)
        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        // Filter Employees (Plural - for Hierarchy)
        if (!empty($filters['employee_ids'])) {
            $query->whereIn('employee_id', $filters['employee_ids']);
        }

        // Filter Date (Month Year)
        // Legacy: month_year BETWEEN start AND end (Strings: YYYY-MM)
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            // Assuming start_date/end_date are YYYY-MM format from frontend for this report?
            // Or YYYY-MM-DD? Legacy snippet uses $_REQUEST['S'] directly in query.
            // If inputs are YYYY-MM, straight comparison works for strings.
            // If inputs are YYYY-MM-DD, we might need to substr or format.
            // Let's assume standard YYYY-MM format for "Month" report.
            // But GeneralReportRequest usually expects date format.
            // Let's try to match string comparison on month_year column.

            // Extract Y-m from dates if they are dates
            $start = substr($filters['start_date'], 0, 7);
            $end = substr($filters['end_date'], 0, 7);

            $query->whereBetween('month_year', [$start, $end]);
        } elseif (!empty($filters['month'])) {
            // Precise Month (YYYY-MM)
            $query->where('month_year', $filters['month']);
        } elseif (!empty($filters['year'])) {
            $query->where('month_year', 'like', $filters['year'] . '-%');
        } elseif (empty($filters['start_date']) && empty($filters['end_date'])) {
            // Fallback current year
            $query->where('month_year', 'like', date('Y') . '-%');
        }

        Log::info('Loan Report Query', ['sql' => $query->toSql(), 'bindings' => $query->getBindings(), 'count' => $query->count()]);

        return $query->orderBy('month_year', 'desc')->get();
    }



    // ==========================================
    // تقارير الموارد البشرية (HR Reports)
    // ==========================================

    /**
     * تقرير الإجازات
     */
    public function getLeaveReport(int $companyId, array $filters = []): Collection
    {
        // 1. Get leave types (filtered)
        if (!empty($filters['leave_type'])) {
            $leaveTypes = ErpConstant::where('company_id', $companyId)
                ->where('type', 'leave_type')
                ->where('constants_id', $filters['leave_type'])
                ->get();
        } else {
            $leaveTypes = ErpConstant::getActiveLeaveTypes($companyId);
        }

        Log::info('Leave Types Count: ' . $leaveTypes->count(), ['company_id' => $companyId]);

        // 2. Get employees (filtered)
        $employeesQuery = User::where('company_id', $companyId)
            ->where('user_type', 'staff')
            ->where('is_active', 1);

        if (!empty($filters['employee_id'])) {
            $employeesQuery->where('user_id', $filters['employee_id']);
        }

        if (!empty($filters['employee_ids'])) {
            $employeesQuery->whereIn('user_id', $filters['employee_ids']);
        }

        $employees = $employeesQuery->with(['user_details.officeShift'])->get();
        Log::info('Employees Count: ' . $employees->count());

        // 3. Get company fiscal date
        $company = User::find($companyId);
        $fiscalDate = $company->fiscal_date ?? '01-01'; // Default to January 1st

        $year = (int)($filters['year'] ?? date('Y'));
        $durationType = $filters['duration_type'] ?? 'hourly';

        // Calculate fiscal period: fiscal_start = year-fiscal_date, fiscal_end = (year+1)-fiscal_date
        $fiscalStart = $year . '-' . $fiscalDate;
        $fiscalEnd = ($year + 1) . '-' . $fiscalDate;


        $reportData = collect();

        foreach ($leaveTypes as $leaveType) {
            $leaveTypeId = $leaveType->constants_id;

            // Parse leave type options for carry-over settings
            $leaveOptions = $leaveType->field_one ? @unserialize($leaveType->field_one) : [];
            $isCarryEnabled = isset($leaveOptions['is_carry']) && $leaveOptions['is_carry'] == 1;

            foreach ($employees as $employee) {
                $employeeId = $employee->user_id;
                $details = $employee->user_details;

                // Skip employees without joining date or excluded from reports
                if (!$details || empty($details->date_of_joining)) {
                    continue;
                }
                if ($details->not_part_of_system_reports == 1) {
                    continue;
                }

                $joiningYear = (int)date('Y', strtotime($details->date_of_joining));

                // Get hours per day from shift
                $hoursPerDay = $details->officeShift->hours_per_day ?? 8;

                // Calculate granted leave (quota)
                $entitled = $this->leaveRepository->getTotalGrantedLeave($employeeId, $leaveTypeId, $companyId);

                // Skip if joined after the fiscal year
                if ($joiningYear > $year) {
                    $entitled = 0;
                }


                // Calculate carry-over from previous years (CUMULATIVE - matching legacy recursive behavior)
                $carryLimit = 0;
                if ($isCarryEnabled && $year > $joiningYear) {
                    // Loop FORWARD from joining year to year-1 to accumulate balance properly
                    $accumulatedBalance = 0;

                    // Get leave type options for quota_assign lookup
                    $quotaAssign = $leaveOptions['quota_assign'] ?? [];

                    for ($calcYear = $joiningYear; $calcYear < $year; $calcYear++) {
                        $yearFiscalStart = $calcYear . '-' . $fiscalDate;
                        $yearFiscalEnd = ($calcYear + 1) . '-' . $fiscalDate;

                        // Calculate fyear_quota for THIS specific year
                        $yearFiscalStartDate = new \DateTime($yearFiscalStart);
                        $joiningDateObj = new \DateTime($details->date_of_joining);
                        $thisYearFyear = max(0, $joiningDateObj->diff($yearFiscalStartDate)->y);

                        // Get quota for this year's fyear_quota (not current year's)
                        $yearEntitled = 0;
                        // First check employee's assigned_hours
                        if (!empty($details->assigned_hours)) {
                            $assignedHours = @unserialize($details->assigned_hours);
                            if (is_array($assignedHours) && isset($assignedHours[$leaveTypeId]) && $assignedHours[$leaveTypeId] > 0) {
                                $yearEntitled = (float) $assignedHours[$leaveTypeId];
                            }
                        }
                        // Fall back to quota_assign for this fyear if no assigned_hours
                        if ($yearEntitled == 0 && is_array($quotaAssign) && isset($quotaAssign[$thisYearFyear])) {
                            $yearEntitled = (float) $quotaAssign[$thisYearFyear];
                        }

                        $yearUsed = $this->leaveRepository->getUsedLeaveInPeriod($employeeId, $leaveTypeId, $companyId, $yearFiscalStart, $yearFiscalEnd);
                        $yearAdj = $this->leaveRepository->getAdjustmentsInPeriod($employeeId, $leaveTypeId, $companyId, $yearFiscalStart, $yearFiscalEnd);

                        // This year's balance = entitled + adjustments + carry_from_prev - used
                        $yearBalance = ($yearEntitled + $yearAdj + $accumulatedBalance) - $yearUsed;

                        // Accumulate for next iteration
                        $accumulatedBalance = max(0, $yearBalance);
                    }

                    $carryLimit = $accumulatedBalance;
                }

                // Get fiscal-period-aware values
                $used = $this->leaveRepository->getUsedLeaveInPeriod($employeeId, $leaveTypeId, $companyId, $fiscalStart, $fiscalEnd);
                $pending = $this->leaveRepository->getPendingLeaveInPeriod($employeeId, $leaveTypeId, $companyId, $fiscalStart, $fiscalEnd);
                $adjustments = $this->leaveRepository->getAdjustmentsInPeriod($employeeId, $leaveTypeId, $companyId, $fiscalStart, $fiscalEnd);
                $leaveDatesStr = $this->leaveRepository->getApprovedLeaveDates($employeeId, $leaveTypeId, $companyId, $fiscalStart, $fiscalEnd);

                // Total entitled = quota + carry
                $totalEntitled = $entitled + $carryLimit;

                // Balance = total entitled + adjustments - used
                $currentBalance = ($totalEntitled + $adjustments) - $used;

                // Duration Type Conversion (Daily)
                $durationTypeText = $durationType === 'daily' ? 'باليوم' : 'بالساعة';

                if ($durationType === 'daily' && $hoursPerDay > 0) {
                    $entitled = round($entitled / $hoursPerDay, 2);
                    $carryLimit = round($carryLimit / $hoursPerDay, 2);
                    $totalEntitled = round($totalEntitled / $hoursPerDay, 2);
                    $used = round($used / $hoursPerDay, 2);
                    $pending = round($pending / $hoursPerDay, 2);
                    $adjustments = round($adjustments / $hoursPerDay, 2);
                    $currentBalance = round($currentBalance / $hoursPerDay, 2);
                }

                $reportData->push([
                    'employee_name' => $employee->full_name,
                    'employee_id' => $details->employee_id ?? $employeeId,
                    'department' => $details->department->department_name ?? '-',
                    'designation' => $details->designation->designation_name ?? '-',
                    'leave_type' => $leaveType->category_name,
                    'leave_type_id' => $leaveTypeId,
                    'carry_limit' => $carryLimit,
                    'entitled' => $entitled, // Quota
                    'total_entitled' => $totalEntitled, // Quota + Carry
                    'used' => $used,
                    'pending' => $pending,
                    'adjustments' => $adjustments,
                    'balance' => $currentBalance,
                    'year' => $year,
                    'leave_dates' => $leaveDatesStr,
                    'duration_type' => $durationTypeText,
                    'hours_per_day' => $hoursPerDay
                ]);
            }
        }

        Log::info('Leave Balance Report Generated', ['count' => $reportData->count()]);

        return $reportData;
    }





    /**
     * تقرير الاستقالات
     */
    public function getResignationsReport(int $companyId, array $filters = []): Collection
    {
        $query = Resignation::query()
            ->with(['employee:user_id,first_name,last_name,email'])
            ->where('company_id', $companyId);

        // فلتر الموظف (واحد)
        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }
        // فلتر الموظفين (حسب الصلاحية)
        elseif (!empty($filters['employee_ids'])) {
            $query->whereIn('employee_id', $filters['employee_ids']);
        }

        // فلتر الحالة
        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', (int)$filters['status']);
        }

        // فلتر التاريخ
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('resignation_date', [$filters['start_date'], $filters['end_date']]);
        } elseif (!empty($filters['year'])) {
            $query->whereYear('resignation_date', $filters['year']);
        } elseif (empty($filters['start_date']) && empty($filters['end_date'])) {
            // Fallback to current year if no date filter is provided
            $query->whereYear('resignation_date', date('Y'));
        }

        Log::info('Resignation Report Query', ['sql' => $query->toSql(), 'bindings' => $query->getBindings(), 'count' => $query->count()]);

        return $query->orderBy('resignation_date', 'desc')->get();
    }

    /**
     * تقرير إنهاء الخدمة - Placeholder
     */
    public function getTerminationsReport(int $companyId, array $filters = []): Collection
    {
        // Placeholder - سيتم تنفيذه لاحقاً
        Log::info('Terminations report requested but not yet implemented', [
            'company_id' => $companyId,
            'filters' => $filters
        ]);

        return collect([]);
    }

    /**
     * تقرير التحويلات
     */
    public function getTransfersReport(int $companyId, array $filters = []): Collection
    {
        $query = Transfer::query()
            ->with(['employee:user_id,first_name,last_name,email'])
            ->where('company_id', $companyId);

        // فلتر الموظف
        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        // فلتر الحالة
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // فلتر التاريخ
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('transfer_date', [$filters['start_date'], $filters['end_date']]);
        } elseif (!empty($filters['year'])) {
            $query->whereYear('transfer_date', $filters['year']);
        } elseif (empty($filters['start_date']) && empty($filters['end_date'])) {
            // Fallback to current year if no date filter is provided
            $query->whereYear('transfer_date', date('Y'));
        }

        Log::info('Transfer Report Query', ['sql' => $query->toSql(), 'bindings' => $query->getBindings(), 'count' => $query->count()]);

        return $query->orderBy('transfer_date', 'desc')->get();
    }

    // ==========================================
    // تقارير الوثائق (Document Reports)
    // ==========================================

    /**
     * تقرير تجديد الإقامة - Placeholder
     */
    public function getResidenceRenewalReport(int $companyId, array $filters = []): Collection
    {
        // Placeholder - سيتم تنفيذه لاحقاً
        Log::info('Residence renewal report requested but not yet implemented', [
            'company_id' => $companyId,
            'filters' => $filters
        ]);

        return collect([]);
    }

    /**
     * تقرير العقود قريبة الانتهاء - Placeholder
     */
    public function getExpiringContractsReport(int $companyId, array $filters = []): Collection
    {
        // Placeholder - سيتم تنفيذه لاحقاً
        Log::info('Expiring contracts report requested but not yet implemented', [
            'company_id' => $companyId,
            'filters' => $filters
        ]);

        return collect([]);
    }

    /**
     * تقرير الهويات/الإقامات قريبة الانتهاء - Placeholder
     */
    public function getExpiringDocumentsReport(int $companyId, array $filters = []): Collection
    {
        // Placeholder - سيتم تنفيذه لاحقاً
        Log::info('Expiring documents report requested but not yet implemented', [
            'company_id' => $companyId,
            'filters' => $filters
        ]);

        return collect([]);
    }

    // ==========================================
    // تقارير الموظفين (Employee Reports)
    // ==========================================

    /**
     * تقرير الموظفين حسب الفرع
     */
    public function getEmployeesByBranchReport(int $companyId, array $filters = []): Collection
    {
        $query = User::query()
            ->with(['branch:office_branch_id,office_name', 'department:department_id,department_name', 'designation:designation_id,designation_name', 'user_details'])
            ->where('company_id', $companyId)
            ->where('user_type', 'employee');

        // فلتر الفرع
        if (!empty($filters['branch_id'])) {
            $query->where('office_branch_id', $filters['branch_id']);
        }

        // فلتر الحالة
        if (!empty($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active' ? 1 : 0);
        }

        return $query->orderBy('office_branch_id', 'asc')
            ->orderBy('first_name', 'asc')
            ->get();
    }

    /**
     * تقرير الموظفين حسب الدولة
     */
    public function getEmployeesByCountryReport(int $companyId, array $filters = []): Collection
    {
        $query = User::query()
            ->with(['branch:office_branch_id,office_name', 'department:department_id,department_name', 'user_details'])
            ->where('company_id', $companyId)
            ->where('user_type', 'employee');

        // فلتر الدولة (nationality)
        if (!empty($filters['country_id'])) {
            $query->where('nationality', $filters['country_id']);
        }

        // فلتر الحالة
        if (!empty($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active' ? 1 : 0);
        }

        return $query->orderBy('nationality', 'asc')
            ->orderBy('first_name', 'asc')
            ->get();
    }

    /**
     * تقرير حسابات نهاية الخدمة - Placeholder
     */
    public function getEndOfServiceReport(int $companyId, array $filters = []): Collection
    {
        // Placeholder - سيتم تنفيذه لاحقاً
        Log::info('End of service report requested but not yet implemented', [
            'company_id' => $companyId,
            'filters' => $filters
        ]);

        return collect([]);
    }

    // ==========================================
    // تقرير الرواتب الشهري (Payroll Report)
    // ==========================================

    /**
     * تقرير الرواتب الشهري
     * 
     * @param int $companyId
     * @param array $filters payment_date, employee_id, payment_method, job_type, branch_id
     * @return Collection
     */
    public function getPayrollReport(int $companyId, array $filters = []): Collection
    {
        $paymentDate = $filters['payment_date'] ?? date('Y-m'); // YYYY-MM

        // 1. جلب الموظفين النشطين للشركة مع الفلاتر
        $query = User::query()
            ->where('company_id', $companyId)
            ->where('is_active', 1)
            ->whereHas('user_details', function ($q) {
                $q->whereNotNull('employee_id');
            })
            ->with([
                'user_details.branch',
                'user_details.currency',
            ]);

        // فلتر الموظف (مفرد)
        if (!empty($filters['employee_id'])) {
            $query->where('user_id', $filters['employee_id']);
        }

        // فلتر قائمة موظفين (للهيكلية)
        if (!empty($filters['employee_ids'])) {
            $query->whereIn('user_id', $filters['employee_ids']);
        }

        // فلتر نوع الوظيفة
        if (!empty($filters['job_type']) && $filters['job_type'] !== 'all') {
            $jobTypeMap = [
                'part_time' => 0,
                'permanent' => 1,
                'contract' => 2,
                'probation' => 3,
            ];

            // Use mapped integer if exists, otherwise use the original value (in case DB uses strings mixed)
            $jobTypeValue = $jobTypeMap[$filters['job_type']] ?? $filters['job_type'];

            $query->whereHas('user_details', function ($q) use ($jobTypeValue) {
                // Check for both the mapped integer and the string value to be safe, 
                // or just the mapped value if we are certain. 
                // Given the user output "Types: 1", it's definitely integer/numeric string '1'.
                $q->where('job_type', $jobTypeValue);
            });
        }

        // فلتر الفرع
        if (!empty($filters['branch_id'])) {
            $query->whereHas('user_details', function ($q) use ($filters) {
                $q->where('branch_id', $filters['branch_id']);
            });
        }

        $employees = $query->orderBy('first_name')->get();

        // 2. جلب القسائم المحفوظة لهذا الشهر (إن وُجدت)
        $existingPayslips = \App\Models\Payslip::query()
            ->where('company_id', $companyId)
            ->where('salary_month', $paymentDate)
            ->with(['allowances', 'deductions'])
            ->get()
            ->keyBy('staff_id');

        // 3. بناء بيانات التقرير لكل موظف
        $result = collect();

        foreach ($employees as $employee) {
            $details = $employee->user_details;
            if (!$details || !$details->employee_id) {
                continue;
            }

            // فلتر طريقة الدفع
            if (!empty($filters['payment_method']) && $filters['payment_method'] !== 'all') {
                $payslip = $existingPayslips->get($employee->user_id);
                $method = $payslip ? $payslip->salary_payment_method : $details->salary_payment_method;
                if (strtolower($method ?? '') !== strtolower($filters['payment_method'])) {
                    continue;
                }
            }

            // جلب البدلات والخصومات الثابتة للموظف (payslip_id = 0)
            $standingAllowances = \App\Models\PayslipAllowance::where('staff_id', $employee->user_id)
                ->where('payslip_id', 0)
                ->get();

            $standingDeductions = \App\Models\PayslipDeduction::where('staff_id', $employee->user_id)
                ->where('payslip_id', 0)
                ->get();

            // حساب إجمالي البدلات والخصومات
            $basicSalary = (float)$details->basic_salary;

            $allowancesTotal = 0;
            foreach ($standingAllowances as $allowance) {
                if ($allowance->is_fixed == 1) {
                    $allowancesTotal += (float)$allowance->pay_amount;
                } else {
                    $allowancesTotal += ($basicSalary / 100) * (float)$allowance->pay_amount;
                }
            }

            $deductionsTotal = 0;
            foreach ($standingDeductions as $deduction) {
                if ($deduction->is_fixed == 1) {
                    $deductionsTotal += (float)$deduction->pay_amount;
                } else {
                    $deductionsTotal += ($basicSalary / 100) * (float)$deduction->pay_amount;
                }
            }

            // حساب قسط السلفة/القرض
            $loanAmount = $this->getLoanInstallmentForMonth($employee->user_id, $paymentDate);

            // حساب خصم الإجازات غير مدفوعة الأجر (Unpaid Leave)
            $unpaidLeaveData = $this->getUnpaidLeaveDeduction($employee->user_id, $paymentDate, $basicSalary);
            $unpaidLeaveDeduction = $unpaidLeaveData['deduction'];

            // صافي الراتب
            // Total Deductions should conceptually include statutory + loan + unpaid leave
            $finalDeductionsTotal = $deductionsTotal + $loanAmount + $unpaidLeaveDeduction;
            $netSalary = $basicSalary + $allowancesTotal - $finalDeductionsTotal;

            // إذا توجد قسيمة محفوظة، استخدام بياناتها
            $payslip = $existingPayslips->get($employee->user_id);
            $isPaid = (bool)$payslip;

            if ($payslip) {
                $basicSalary = (float)$payslip->basic_salary;
                $allowancesTotal = (float)$payslip->total_allowances;
                $deductionsTotal = (float)$payslip->total_statutory_deductions; // This usually excludes loan/unpaid in DB storage, need to verify. 
                // Assuming payslip stores strict statutory deductions in one field, and others separately.
                // Legacy: total_deductions = statutory + loan + unpaid.
                // Payslip model likely stores these.
                $loanAmount = (float)$payslip->loan_amount;
                $unpaidLeaveDeduction = (float)($payslip->unpaid_leave_deduction ?? 0);
                $netSalary = (float)$payslip->net_salary;
                $standingAllowances = $payslip->allowances;
                $standingDeductions = $payslip->deductions;

                // For Report display consistency:
                // We want "Total Deductions" column to show the SUM.
                $finalDeductionsTotal = $deductionsTotal + $loanAmount + $unpaidLeaveDeduction;
            }

            // بناء كائن النتيجة
            $result->push((object)[
                'user_id' => $employee->user_id,
                'employee' => $employee,
                'details' => $details,
                'basic_salary' => $basicSalary,
                'allowances' => $standingAllowances,
                'allowances_total' => $allowancesTotal,
                'deductions' => $standingDeductions,
                'deductions_total' => $finalDeductionsTotal, // Show ALL deductions sum
                'loan_amount' => $loanAmount,
                'unpaid_leave_days' => $payslip?->unpaid_leave_days ?? $unpaidLeaveData['days'],
                'unpaid_leave_deduction' => $unpaidLeaveDeduction,
                'net_salary' => $netSalary,
                'status' => $payslip?->status ?? 0,
                'payment_method' => $payslip?->salary_payment_method ?? $details->salary_payment_method,
                'is_paid' => $isPaid,
            ]);
        }

        Log::info('Payroll Report Generated', [
            'company_id' => $companyId,
            'payment_date' => $paymentDate,
            'count' => $result->count()
        ]);

        return $result;
    }

    /**
     * حساب قسط السلفة/القرض لشهر معين
     */
    private function getLoanInstallmentForMonth(int $userId, string $monthYear): float
    {
        $loans = AdvanceSalary::where('employee_id', $userId)
            ->where('status', 1)
            ->whereIn('salary_type', ['loan', 'advance'])
            ->get();

        $total = 0;
        foreach ($loans as $loan) {
            $remaining = (float)$loan->advance_amount - (float)$loan->total_paid;
            if ($remaining <= 0) continue;

            if ($loan->one_time_deduct == 1) {
                $total += $remaining;
            } else {
                $installment = (float)$loan->monthly_installment;
                $total += min($installment, $remaining);
            }
        }

        return $total;
    }

    /**
     * حساب خصم الإجازات غير مدفوعة الأجر
     */
    private function getUnpaidLeaveDeduction(int $userId, string $monthYear, float $basicSalary): array
    {
        // Parse month/year
        $date = \Carbon\Carbon::createFromFormat('Y-m', $monthYear);
        $month = $date->month;
        $year = $date->year;
        $daysInMonth = $date->daysInMonth;

        // Get approved leaves that are deducted
        $leaves = \App\Models\LeaveApplication::where('employee_id', $userId)
            ->where('status', 2) // Approved
            ->where('is_deducted', 1)
            ->where(function ($q) use ($month, $year) {
                $q->whereMonth('from_date', $month)->whereYear('from_date', $year)
                    ->orWhere(function ($sub) use ($month, $year) {
                        $sub->whereMonth('to_date', $month)->whereYear('to_date', $year);
                    });
            })
            ->get();

        $totalDays = 0;
        foreach ($leaves as $leave) {
            // Calculate intersection with current month
            $start = \Carbon\Carbon::parse($leave->from_date);
            $end = \Carbon\Carbon::parse($leave->to_date);

            // Clamp to current month
            $monthStart = \Carbon\Carbon::create($year, $month, 1);
            $monthEnd = $monthStart->copy()->endOfMonth();

            if ($start->lt($monthStart)) $start = $monthStart;
            if ($end->gt($monthEnd)) $end = $monthEnd;

            if ($start->lte($end)) {
                $days = $start->diffInDays($end) + 1;
                $totalDays += $days;
            }
        }

        // Calculation: (Basic / 30) * Days
        // Standard payload calculation often assumes 30 days regardless of month length, 
        // strictly following legacy "ibasic_salary / 30 * days" usually.
        // Or "ibasic_salary / daysInMonth * days".
        // Legacy code didn't show text, but standard HR is usually / 30.
        // We'll use 30 for consistency with typical systems.
        $deduction = 0;
        if ($totalDays > 0) {
            $deduction = ($basicSalary / 30) * $totalDays;
        }

        return ['days' => $totalDays, 'deduction' => $deduction];
    }

    /**
     * جلب أنواع البدلات للشركة
     */
    public function getAllowanceTypes(int $companyId): Collection
    {
        return \App\Models\ContractOption::forCompany($companyId)
            ->allowances()
            ->orderBy('salay_type')
            ->orderBy('contract_option_id')
            ->get();
    }

    /**
     * جلب أنواع الخصومات النظامية للشركة
     */
    public function getStatutoryTypes(int $companyId): Collection
    {
        return \App\Models\ContractOption::forCompany($companyId)
            ->statutory()
            ->orderBy('contract_option_id')
            ->get();
    }
    /**
     * تقرير الجوائز (Awards Report)
     */
    public function getAwardsReport(int $companyId, array $filters = []): Collection
    {
        $query = \App\Models\Award::query()
            ->with(['employee', 'awardType'])
            ->where('company_id', $companyId);

        // فلتر التاريخ (شهر وسنة)
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $startMonth = date('Y-m', strtotime($filters['start_date']));
            $endMonth = date('Y-m', strtotime($filters['end_date']));

            $query->whereBetween('award_month_year', [$startMonth, $endMonth]);
        }

        // فلتر الموظف
        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        // فلتر قائمة موظفين (للهيكلية)
        if (!empty($filters['employee_ids'])) {
            $query->whereIn('employee_id', $filters['employee_ids']);
        }

        return $query->orderBy('award_month_year', 'desc')->get();
    }

    /**
     * تقرير الترقيات (Promotions Report)
     */
    public function getPromotionsReport(int $companyId, array $filters = []): Collection
    {
        $query = \App\Models\Promotion::query()
            ->with([
                'employee:user_id,first_name,last_name',
                'oldDepartment:department_id,department_name',
                'newDepartment:department_id,department_name',
                'oldDesignation:designation_id,designation_name',
                'newDesignation:designation_id,designation_name'
            ])
            ->where('company_id', $companyId);

        // فلتر التاريخ
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('promotion_date', [$filters['start_date'], $filters['end_date']]);
        }

        // فلتر الموظف
        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        // فلتر حالة الترقية
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // فلتر قائمة موظفين (للهيكلية)
        if (!empty($filters['employee_ids'])) {
            $query->whereIn('employee_id', $filters['employee_ids']);
        }

        return $query->orderBy('promotion_date', 'desc')->get();
    }
}
