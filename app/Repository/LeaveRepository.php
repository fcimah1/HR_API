<?php

namespace App\Repository;

use App\Repository\Interface\LeaveRepositoryInterface;
use App\DTOs\Leave\LeaveApplicationFilterDTO;
use App\DTOs\Leave\CreateLeaveApplicationDTO;
use App\DTOs\Leave\UpdateLeaveApplicationDTO;
use App\DTOs\Leave\CreateLeaveTypeDTO;
use App\Models\LeaveApplication;
use App\Models\ErpConstant;
use App\Models\LeaveAdjustment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class LeaveRepository implements LeaveRepositoryInterface
{
    /**
     * Get paginated leave applications with filters
     */
    public function getPaginatedApplications(LeaveApplicationFilterDTO $filters): LengthAwarePaginator
    {
    $companyId = $filters->companyId ;
        $query = LeaveApplication::where('company_id', $companyId)->with(['employee', 'dutyEmployee', 'leaveType']);

        // Apply filters
        if ($filters->companyName !== null) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('company_name', $filters->companyName);
            });
        }

        if ($filters->employeeId !== null) {
            $query->where('employee_id', $filters->employeeId);
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        if ($filters->leaveTypeId !== null) {
            $query->where('leave_type_id', $filters->leaveTypeId);
        }

        if ($filters->fromDate !== null) {
            $query->where('from_date', '>=', $filters->fromDate);
        }

        if ($filters->toDate !== null) {
            $query->where('to_date', '<=', $filters->toDate);
        }

        // Apply sorting
        $query->orderBy($filters->sortBy, $filters->sortDirection);

        return $query->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }

    /**
     * Create a new leave application
     */
    public function createApplication(CreateLeaveApplicationDTO $dto): LeaveApplication
    {
        $application = LeaveApplication::create($dto->toArray());
        $application->load(['employee', 'dutyEmployee', 'leaveType']);
        
        return $application;
    }

    /**
     * Find leave application by ID
     */
    public function findApplication(int $id): ?LeaveApplication
    {
        return LeaveApplication::with(['employee', 'dutyEmployee', 'leaveType'])
            ->find($id);
    }

    /**
     * Find leave application by ID for specific company
     */
    public function findApplicationInCompany(int $id, int $companyId): ?LeaveApplication
    {
        return LeaveApplication::with(['employee', 'dutyEmployee', 'leaveType'])
            ->where('leave_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * Find leave application by ID for specific employee
     */
    public function findApplicationForEmployee(int $id, int $employeeId): ?LeaveApplication
    {
        return LeaveApplication::with(['employee', 'dutyEmployee', 'leaveType'])
            ->where('leave_id', $id)
            ->where('employee_id', $employeeId)
            ->first();
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
                $application->load(['employee', 'dutyEmployee', 'leaveType']);
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
            'status' => true,
            'remarks' => $remarks,
        ]);

        $application->refresh();
        $application->load(['employee', 'dutyEmployee', 'leaveType']);

        return $application;
    }

    /**
     * Reject leave application
     */
    public function rejectApplication(LeaveApplication $application, int $rejectedBy, string $reason): LeaveApplication
    {
        $application->update([
            'status' => false,
            'remarks' => $reason,
        ]);

        $application->refresh();
        $application->load(['employee', 'dutyEmployee', 'leaveType']);

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
            'pending_applications' => LeaveApplication::where('company_id', $companyId)->where('status', false)->count(),
            'approved_applications' => LeaveApplication::where('company_id', $companyId)->where('status', true)->count(),
        ];

        $adjustmentStats = [
            'effective_company_id' => $companyId,
            'total_adjustments' => LeaveAdjustment::where('company_id', $companyId)->count(),
            'pending_adjustments' => LeaveAdjustment::where('company_id', $companyId)->where('status', LeaveAdjustment::STATUS_PENDING)->count(),
            'approved_adjustments' => LeaveAdjustment::where('company_id', $companyId)->where('status', LeaveAdjustment::STATUS_APPROVED)->count(),
        ];

        return [
            'applications' => $applicationStats,
            'adjustments' => $adjustmentStats,
        ];
    }

    /**
     * Get active leave types for company
     */
    public function getActiveLeaveTypes(int $companyId): Collection
    {
        return ErpConstant::getActiveLeaveTypes($companyId);
    }

    /**
     * Create leave type
     */
    public function createLeaveType(CreateLeaveTypeDTO $dto): object
    {
        // Check if leave type already exists for this company
        $existingLeaveType = ErpConstant::where('company_id', $dto->companyId)
            ->where('type', ErpConstant::TYPE_LEAVE_TYPE)
            ->where('category_name', $dto->name)
            ->first();
        
        if ($existingLeaveType) {
            throw new \Exception('نوع الإجازة "' . $dto->name . '" موجود بالفعل لهذه الشركة');
        }
        
        return ErpConstant::create($dto->toArray());
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

        $details = $employee->details()->first();

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
            ->where('status', true)
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
            ->where('status', false)
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
}
