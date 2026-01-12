<?php

namespace App\Repository;

use App\DTOs\Leave\CreateHourlyLeaveDTO;
use Illuminate\Support\Facades\DB;
use App\Repository\Interface\LeaveRepositoryInterface;
use App\DTOs\Leave\LeaveApplicationFilterDTO;
use App\DTOs\Leave\CreateLeaveApplicationDTO;
use App\DTOs\Leave\UpdateLeaveApplicationDTO;
use App\Models\LeaveApplication;
use App\Models\ErpConstant;
use App\Models\LeaveAdjustment;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class LeaveRepository implements LeaveRepositoryInterface
{
    /**
     * Get paginated leave applications with filters
     */
    /**
     * الحصول على طلبات الإجازات مع التصفية والترقيم الصفحي
     */
    public function getPaginatedApplications(LeaveApplicationFilterDTO $filters, User $user): array
    {
        $companyId = $filters->companyId;
        $query = LeaveApplication::where('company_id', $companyId)
            ->where('leave_hours', '>=', 8)
            ->with(['employee', 'dutyEmployee', 'leaveType', 'approvals.staff']);

        // تطبيق فلتر البحث
        if ($filters->search !== null && trim($filters->search) !== '') {
            $searchTerm = '%' . $filters->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                // البحث في بيانات الموظف
                $q->whereHas('employee', function ($subQuery) use ($searchTerm) {
                    $subQuery->where('first_name', 'like', $searchTerm)
                        ->orWhere('last_name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm);
                });

                // البحث في بيانات موظف المناوبة
                $q->orWhereHas('dutyEmployee', function ($subQuery) use ($searchTerm) {
                    $subQuery->where('first_name', 'like', $searchTerm)
                        ->orWhere('last_name', 'like', $searchTerm);
                });

                // البحث في نوع الإجازة
                $q->orWhereHas('leaveType', function ($subQuery) use ($searchTerm) {
                    $subQuery->where('category_name', 'like', $searchTerm);
                });

                // البحث في حقول الإجازة نفسها
                $q->orWhere('reason', 'like', $searchTerm);
                $q->orWhere('from_date', 'like', $searchTerm);
                $q->orWhere('to_date', 'like', $searchTerm);
                $q->orWhere('status', 'like', $searchTerm);

                // البحث في الموظفين الذين قاموا بالموافقة (nested relationship)
                $q->orWhereHas('approvals.staff', function ($approvalQuery) use ($searchTerm) {
                    $approvalQuery->where('first_name', 'like', $searchTerm)
                        ->orWhere('last_name', 'like', $searchTerm);
                });
            });
        }

        // تطبيق الفلاتر الأخرى
        if ($filters->companyName !== null) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('company_name', $filters->companyName);
            });
        }

        // فلتر معرف الموظف
        if ($filters->employeeId !== null) {
            $query->where('employee_id', $filters->employeeId);
        }

        // فلتر معرفات الموظفين (للتبعية)
        if ($filters->employeeIds !== null && is_array($filters->employeeIds) && !empty($filters->employeeIds)) {
            $query->whereIn('employee_id', $filters->employeeIds);
        }

        // فلتر الحالة
        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        // فلتر نوع الإجازة
        if ($filters->leaveTypeId !== null) {
            $query->where('leave_type_id', $filters->leaveTypeId);
        }

        // Exclude restricted leave types
        if ($filters->excludedLeaveTypeIds !== null && !empty($filters->excludedLeaveTypeIds)) {
            $query->whereNotIn('leave_type_id', $filters->excludedLeaveTypeIds);
        }

        // فلتر تاريخ البداية
        if ($filters->fromDate !== null) {
            $query->where('from_date', '>=', $filters->fromDate);
        }

        // فلتر تاريخ النهاية
        if ($filters->toDate !== null) {
            $query->where('to_date', '<=', $filters->toDate);
        }

        // تطبيق الفرز
        $sortBy = in_array($filters->sortBy, ['created_at', 'from_date', 'to_date', 'status'])
            ? $filters->sortBy
            : 'created_at';

        $sortDirection = strtolower($filters->sortDirection) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $paginator = $query->paginate($filters->perPage, ['*'], 'page', $filters->page);

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /**
     * Create a new leave application from DTO
     */
    public function createApplication(CreateLeaveApplicationDTO $dto): LeaveApplication
    {
        $application = LeaveApplication::create($dto->toArray());
        $application->load(['employee', 'dutyEmployee', 'leaveType', 'approvals.staff']);

        return $application;
    }


    /**
     * Find leave application by ID
     */
    public function findApplication(int $id): ?LeaveApplication
    {
        return LeaveApplication::with(['employee', 'dutyEmployee', 'leaveType', 'approvals.staff'])->findOrFail($id);
    }

    /**
     * Find leave application by ID for specific company
     */
    public function findApplicationInCompany(int $id, int $companyId): ?LeaveApplication
    {
        return LeaveApplication::where('company_id', $companyId)->findOrFail($id)
            ->load(['employee', 'dutyEmployee', 'leaveType', 'approvals.staff']);
    }

    /**
     * Find leave application by ID for specific employee
     */
    public function findApplicationForEmployee(int $id, int $employeeId): ?LeaveApplication
    {
        return LeaveApplication::where('employee_id', $employeeId)->findOrFail($id)
            ->load(['employee', 'dutyEmployee', 'leaveType', 'approvals.staff']);
    }

    /**
     * Update leave application
     */
    public function update_Application(LeaveApplication $application, UpdateLeaveApplicationDTO $dto): object
    {
        try {
            if ($dto->hasUpdates()) {
                $updates = $dto->toArray();

                // Update using Eloquent's update method (simpler and more reliable)
                $application->update($updates);

                // Refresh to get latest data
                $application->refresh();

                // Load relationships
                $application->load(['employee', 'dutyEmployee', 'leaveType', 'approvals.staff']);
            }

            Log::debug('LeaveRepository::updateApplication - Update completed', [
                'application_id' => $application->leave_id
            ]);

            return $application;
        } catch (\Exception $e) {
            error_log("LeaveRepository::updateApplication - Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Approve leave application
     */
    public function approveApplication(LeaveApplication $application, int $approvedBy, ?string $remarks = null): LeaveApplication
    {
        $application->update([
            'status' => LeaveApplication::STATUS_APPROVED,
            'remarks' => $remarks,
        ]);

        // Note: Approval recording is handled by ApprovalService to avoid duplicates

        $application->refresh();
        $application->load(['employee', 'dutyEmployee', 'leaveType', 'approvals.staff']);

        return $application;
    }

    /**
     * Reject leave application
     */
    public function rejectApplication(LeaveApplication $application, int $rejectedBy, string $reason): LeaveApplication
    {
        $application->update([
            'status' => LeaveApplication::STATUS_REJECTED,
            'remarks' => $reason,
        ]);

        // Note: Rejection recording is handled by ApprovalService to avoid duplicates

        $application->refresh();
        $application->load(['employee', 'dutyEmployee', 'leaveType', 'approvals.staff']);

        return $application;
    }

    /**
     * Get leave statistics for company
     */
    public function getLeaveStatistics(int $companyId): array
    {

        $applicationStats = [
            'total_applications' => LeaveApplication::where('company_id', $companyId)->count(),
            'effective_company_id' => $companyId,
            'pending_applications' => LeaveApplication::where('company_id', $companyId)->where('status', LeaveApplication::STATUS_PENDING)->count(),
            'approved_applications' => LeaveApplication::where('company_id', $companyId)->where('status', LeaveApplication::STATUS_APPROVED)->count(),
            'rejected_applications' => LeaveApplication::where('company_id', $companyId)->where('status', LeaveApplication::STATUS_REJECTED)->count(),
        ];

        $adjustmentStats = [
            'effective_company_id' => $companyId,
            'total_adjustments' => LeaveAdjustment::where('company_id', $companyId)->count(),
            'pending_adjustments' => LeaveAdjustment::where('company_id', $companyId)->where('status', LeaveAdjustment::STATUS_PENDING)->count(),
            'approved_adjustments' => LeaveAdjustment::where('company_id', $companyId)->where('status', LeaveAdjustment::STATUS_APPROVED)->count(),
            'rejected_adjustments' => LeaveAdjustment::where('company_id', $companyId)->where('status', LeaveAdjustment::STATUS_REJECTED)->count(),
        ];

        return [
            'applications' => $applicationStats,
            'adjustments' => $adjustmentStats,
        ];
    }



    /**
     * Get total granted leave for an employee (in hours)
     */
    public function getTotalGrantedLeave(int $employeeId, int $leaveTypeId, int $companyId): float
    {
        // جلب نوع الإجازة (من الشركة أو من الأنواع العامة)
        $leaveType = ErpConstant::where('constants_id', $leaveTypeId)
            ->whereIn('company_id', [$companyId, 0])
            ->orderBy('company_id', 'desc')
            ->first();

        if (!$leaveType) {
            return 0.0;
        }

        // محاولة قراءة إعدادات الإجازة من field_one (مخزن كـ serialize)
        $options = $leaveType->field_one ? @unserialize($leaveType->field_one) : null;

        // إذا لم تكن البيانات مُسلسَلة بشكل صحيح أو لا تحتوي على quota_assign، نرجع للمنطق البسيط (أيام ثابتة)
        if (!is_array($options) || !isset($options['quota_assign']) || ($options['is_quota'] ?? '0') != '1') {
            $days = (float) $leaveType->leave_days;
            return $days * 8.0;
        }

        // جلب بيانات الموظف والشركة لحساب سنوات الخدمة (fyear_quota)
        $employee = User::find($employeeId);
        $company  = User::find($companyId);

        if (!$employee || !$company) {
            // في حال عدم توفر بيانات كافية نستخدم أول قيمة من quota_assign (المخزنة بالساعات) إن وجدت أو نعود للمنطق البسيط
            $quotaAssign = $options['quota_assign'] ?? [];
            if (is_array($quotaAssign) && isset($quotaAssign[0])) {
                return (float) $quotaAssign[0];
            }

            $days = (float) $leaveType->leave_days;
            return $days * 8.0;
        }

        $details = $employee->user_details()->first();

        // إذا لم يوجد تاريخ تعيين واضح نستخدم أول شريحة كافتراضي
        if (!$details || empty($details->date_of_joining)) {
            $quotaAssign = $options['quota_assign'] ?? [];
            if (is_array($quotaAssign) && isset($quotaAssign[0])) {
                return (float) $quotaAssign[0];
            }

            $days = (float) $leaveType->leave_days;
            return $days * 8.0;
        }

        // حساب سنة الملخّص (نفس منطق كود الويب: السنة الحالية)
        $summaryYear = (int) date('Y');

        // تاريخ بداية السنة المالية للشركة (مثلاً 2025-10-31 من fiscal_date = 10-31)
        $fiscalDate = $company->fiscal_date ?: '12-31';

        try {
            $joiningDate = new \DateTime($details->date_of_joining);
            $fiscalStart = new \DateTime($summaryYear . '-' . $fiscalDate);
        } catch (\Exception $e) {
            // في حال وجود خطأ في التواريخ نستخدم أول شريحة
            $quotaAssign = $options['quota_assign'] ?? [];
            if (is_array($quotaAssign) && isset($quotaAssign[0])) {
                return (float) $quotaAssign[0] * 8.0;
            }

            $days = (float) $leaveType->leave_days;
            return $days * 8.0;
        }

        // فرق السنوات بين تاريخ التعيين وبداية السنة المالية الحالية
        $diff = $joiningDate->diff($fiscalStart);
        $fyearQuota = $diff->y;

        // حصر القيمة ضمن حدود مصفوفة quota_assign (عادة 0..49)
        if ($fyearQuota < 0) {
            $fyearQuota = 0;
        }
        if ($fyearQuota > 49) {
            $fyearQuota = 49;
        }

        $quotaAssign = $options['quota_assign'] ?? [];

        if (is_array($quotaAssign) && isset($quotaAssign[$fyearQuota])) {
            // quota_assign مخزنة بالساعات مباشرة
            return (float) $quotaAssign[$fyearQuota];
        }

        // في حال عدم وجود قيمة لهذه السنة، نحاول استخدام الشريحة الأولى، وإلا نرجع للمنطق القديم
        if (is_array($quotaAssign) && isset($quotaAssign[0])) {
            return (float) $quotaAssign[0];
        }

        $days = (float) $leaveType->leave_days;
        return $days * 8.0;
    }

    /**
     * Get total used leave for an employee (in hours)
     */
    public function getTotalUsedLeave(int $employeeId, int $leaveTypeId, int $companyId): float
    {
        $applications = LeaveApplication::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('company_id', $companyId)
            ->where('status', LeaveApplication::STATUS_APPROVED)
            ->get();

        $totalHours = 0.0;

        foreach ($applications as $application) {
            if (!is_null($application->leave_hours)) {
                $totalHours += (float) $application->leave_hours;
            } elseif ($application->from_date && $application->to_date) {
                try {
                    $from = new \DateTime($application->from_date);
                    $to = new \DateTime($application->to_date);
                    $days = $to->diff($from)->days + 1;
                    $totalHours += $days * 8.0;
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return (float) $totalHours;
    }

    /**
     * Get total pending leave hours for an employee (in hours)
     */
    public function getPendingLeaveHours(int $employeeId, int $leaveTypeId, int $companyId): float
    {
        $applications = LeaveApplication::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('company_id', $companyId)
            ->where('status', LeaveApplication::STATUS_PENDING)
            ->get();

        $totalHours = 0.0;

        foreach ($applications as $application) {
            if (!is_null($application->leave_hours)) {
                $totalHours += (float) $application->leave_hours;
            } elseif ($application->from_date && $application->to_date) {
                try {
                    $from = new \DateTime($application->from_date);
                    $to = new \DateTime($application->to_date);
                    $days = $to->diff($from)->days + 1;
                    $totalHours += $days * 8.0;
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return (float) $totalHours;
    }


    /**
     * Get total approved adjustment hours for an employee (in hours)
     */
    public function getTotalAdjustmentHours(int $employeeId, int $leaveTypeId, int $companyId): float
    {
        return (float) LeaveAdjustment::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('company_id', $companyId)
            ->where('status', LeaveAdjustment::STATUS_APPROVED)
            ->sum('adjust_hours');
    }

    /**
     * Get monthly granted hours for a leave type
     * Returns array with month number as key and granted hours as value
     * 
     * This reads from the staff_details table where leave_options are stored
     * as serialized data containing monthly accrual information.
     * If no monthly data exists, it divides the total granted hours by 12.
     */
    public function getMonthlyGrantedHours(int $employeeId, int $leaveTypeId, int $companyId): array
    {
        // Initialize empty array for 12 months
        $monthlyHours = array_fill(1, 12, 0.0);
        $hasMonthlyData = false;

        // Try to get monthly accrual data from leave_options (if employee details exist)
        $employee = User::find($employeeId);
        if ($employee) {
            $details = $employee->user_details()->first();

            if ($details && !empty($details->leave_options)) {
                $options = @unserialize($details->leave_options);

                if (is_array($options) && isset($options[$leaveTypeId]) && is_array($options[$leaveTypeId])) {
                    // Extract monthly hours for this leave type
                    $leaveTypeOptions = $options[$leaveTypeId];

                    // The structure is: [month_number => hours]
                    for ($month = 1; $month <= 12; $month++) {
                        if (isset($leaveTypeOptions[$month]) && $leaveTypeOptions[$month] > 0) {
                            $monthlyHours[$month] = (float) $leaveTypeOptions[$month];
                            $hasMonthlyData = true;
                        }
                    }
                }
            }
        }

        // If no monthly accrual data exists, distribute total granted hours equally across 12 months
        if (!$hasMonthlyData) {
            $totalGranted = $this->getTotalGrantedLeave($employeeId, $leaveTypeId, $companyId);

            if ($totalGranted > 0) {
                $monthlyAmount = $totalGranted / 12;

                for ($month = 1; $month <= 12; $month++) {
                    $monthlyHours[$month] = (float) $monthlyAmount;
                }
            }
        }

        return $monthlyHours;
    }

    /**
     * Get monthly used hours for a leave type
     * Returns array with month number as key and used hours as value
     * 
     * Calculates used hours from approved leave applications
     */
    public function getMonthlyUsedHours(int $employeeId, int $leaveTypeId, int $companyId, int $year): array
    {
        // Initialize empty array for 12 months
        $monthlyHours = array_fill(1, 12, 0.0);

        // Get all approved applications for this employee and leave type in the specified year
        $applications = LeaveApplication::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('company_id', $companyId)
            ->where('status', LeaveApplication::STATUS_APPROVED)
            ->whereYear('from_date', $year)
            ->get();

        foreach ($applications as $application) {
            // Get the month from from_date
            try {
                $fromDate = new \DateTime($application->from_date);
                $month = (int) $fromDate->format('n'); // 1-12

                // Calculate hours for this application
                $hours = 0.0;
                if (!is_null($application->leave_hours)) {
                    $hours = (float) $application->leave_hours;
                } elseif ($application->from_date && $application->to_date) {
                    $toDate = new \DateTime($application->to_date);
                    $days = $toDate->diff($fromDate)->days + 1;
                    $hours = $days * 8.0;
                }

                $monthlyHours[$month] += $hours;
            } catch (\Exception $e) {
                continue;
            }
        }

        return $monthlyHours;
    }
}
