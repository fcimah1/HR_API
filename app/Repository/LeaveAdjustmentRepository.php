<?php

namespace App\Repository;

use App\DTOs\LeaveAdjustment\CreateLeaveAdjustmentDTO;
use App\DTOs\LeaveAdjustment\UpdateLeaveAdjustmentDTO;
use App\DTOs\LeaveAdjustment\LeaveAdjustmentFilterDTO;
use App\Models\LeaveAdjustment;
use App\Models\StaffApproval;
use App\Repository\Interface\LeaveAdjustmentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LeaveAdjustmentRepository implements LeaveAdjustmentRepositoryInterface
{

    /**
     * Get paginated leave adjustments with filters
     */
    public function getPaginatedAdjustments(LeaveAdjustmentFilterDTO $filters): array
    {
        $companyId = $filters->companyId;
        $query = LeaveAdjustment::where('company_id', $companyId)
            ->with(['employee', 'leaveType', 'approvals.staff']);

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

                // البحث في نوع الإجازة
                $q->orWhereHas('leaveType', function ($subQuery) use ($searchTerm) {
                    $subQuery->where('category_name', 'like', $searchTerm);
                });

                // البحث في السبب
                $q->orWhere('reason_adjustment', 'like', $searchTerm);
                $q->orWhere('status', 'like', $searchTerm);
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

        // تطبيق الفرز
        $sortBy = in_array($filters->sortBy, ['created_at', 'adjustment_date', 'status'])
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
     * Create a new leave adjustment
     */
    public function createAdjust(CreateLeaveAdjustmentDTO $dto): object
    {
        $adjustment = LeaveAdjustment::create($dto->toArray());
        $adjustment->load(['employee', 'leaveType']);

        return $adjustment;
    }

    /**
     * Find leave adjustment by ID
     */
    public function findAdjustment(int $id): ?LeaveAdjustment
    {
        return LeaveAdjustment::with(['employee', 'leaveType', 'approvals.staff'])
            ->find($id);
    }

    /**
     * Find leave adjustment by ID for specific company
     */
    public function findAdjustmentInCompany(int $id, int $companyId): ?LeaveAdjustment
    {
        return LeaveAdjustment::with(['employee', 'leaveType','approvals.staff'])
            ->where('adjustment_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * Approve leave adjustment
     */
    public function approveAdjustment(LeaveAdjustment $adjustment, int $approvedBy): LeaveAdjustment
    {
        $adjustment->update([
            'status' => LeaveAdjustment::STATUS_APPROVED,
        ]);

        // تسجيل سجل الموافقة
        StaffApproval::create([
            'company_id' => $adjustment->company_id,
            'staff_id' => $approvedBy,
            'module_option' => 'leave_adjustment_settings',
            'module_key_id' => $adjustment->adjustment_id,
            'status' => 1, // موافق
            'approval_level' => 1,
            'updated_at' => now(),
        ]);

        $adjustment->refresh();
        $adjustment->load(['employee', 'leaveType', 'approvals.staff']);

        return $adjustment;
    }

    /**
     * Reject leave adjustment
     */
    public function rejectAdjustment(LeaveAdjustment $adjustment, int $rejectedBy, string $reason): LeaveAdjustment
    {
        $adjustment->update([
            'status' => LeaveAdjustment::STATUS_REJECTED,
        ]);
        StaffApproval::create([
            'company_id' => $adjustment->company_id,
            'staff_id' => $rejectedBy,
            'module_option' => 'leave_adjustment_settings',
            'module_key_id' => $adjustment->adjustment_id,
            'status' => 2, // رفض
            'approval_level' => 1,
            'updated_at' => now(),
        ]);
        $adjustment->refresh();

        $adjustment->load(['employee', 'leaveType', 'approvals.staff']);

        return $adjustment;
    }

    /**
     * Find leave adjustment for specific employee
     */
    public function findAdjustmentForEmployee(int $id, int $employeeId): ?LeaveAdjustment
    {
        return LeaveAdjustment::with(['employee', 'leaveType', 'approvals.staff'])
            ->where('adjustment_id', $id)
            ->where('employee_id', $employeeId)
            ->first();
    }

    /**
     * Update leave adjustment
     */
    public function updateAdjustment(LeaveAdjustment $adjustment, UpdateLeaveAdjustmentDTO $dto): LeaveAdjustment

    {
        if ($dto->hasUpdates()) {
            $adjustment->update($dto->toArray());
            $adjustment->refresh();
            $adjustment->load(['employee', 'leaveType', 'approvals.staff']);
        }

        return $adjustment;
    }

    /**
     * Cancel leave adjustment (mark as rejected)
     */
    public function cancelAdjustment(LeaveAdjustment $adjustment, int $cancelledBy, string $reason): LeaveAdjustment
    {
        $adjustment->update([
            'status' => LeaveAdjustment::STATUS_REJECTED,
            'reason_adjustment' => $reason,
        ]);

        StaffApproval::create([
            'company_id' => $adjustment->company_id,
            'staff_id' => $cancelledBy,
            'module_option' => 'leave_adjustment_settings',
            'module_key_id' => $adjustment->adjustment_id,
            'status' => LeaveAdjustment::STATUS_REJECTED,
            'approval_level' => 1,
            'updated_at' => now(),
        ]);

        $adjustment->refresh();
        $adjustment->load(['employee', 'leaveType', 'approvals.staff']);

        return $adjustment;
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
