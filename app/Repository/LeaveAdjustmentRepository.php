<?php

namespace App\Repository;

use App\DTOs\LeaveAdjustment\CreateLeaveAdjustmentDTO;
use App\DTOs\LeaveAdjustment\UpdateLeaveAdjustmentDTO;
use App\DTOs\LeaveAdjustment\LeaveAdjustmentFilterDTO;
use App\Models\LeaveAdjustment;
use App\Repository\Interface\LeaveAdjustmentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LeaveAdjustmentRepository implements LeaveAdjustmentRepositoryInterface
{
   
    /**
     * Get paginated leave adjustments with filters
     */
    public function getPaginatedAdjustments(LeaveAdjustmentFilterDTO $filters): LengthAwarePaginator
    
    {
        $companyId = $filters->companyId ;
        $query = LeaveAdjustment::where('company_id', $companyId)->with(['employee', 'dutyEmployee', 'leaveType']);

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

        // Apply sorting
        $query->orderBy($filters->sortBy, $filters->sortDirection);

        return $query->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }

    /**
     * Create a new leave adjustment
     */
    public function createAdjust(CreateLeaveAdjustmentDTO $dto): object
    {
        $adjustment = LeaveAdjustment::create($dto->toArray());
        $adjustment->load(['employee', 'dutyEmployee', 'leaveType']);
        
        return $adjustment;
    }

    /**
     * Find leave adjustment by ID
     */
    public function findAdjustment(int $id): ?LeaveAdjustment
    {
        return LeaveAdjustment::with(['employee', 'dutyEmployee', 'leaveType'])
            ->find($id);
    }

    /**
     * Find leave adjustment by ID for specific company
     */
    public function findAdjustmentInCompany(int $id, int $companyId): ?LeaveAdjustment
    {
        return LeaveAdjustment::with(['employee', 'dutyEmployee', 'leaveType'])
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

        $adjustment->refresh();
        $adjustment->load(['employee', 'dutyEmployee', 'leaveType']);

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

        $adjustment->refresh();
        $adjustment->load(['employee', 'dutyEmployee', 'leaveType']);

        return $adjustment;
    }

    /**
     * Find leave adjustment for specific employee
     */
    public function findAdjustmentForEmployee(int $id, int $employeeId): ?LeaveAdjustment
    {
        return LeaveAdjustment::with(['employee', 'dutyEmployee', 'leaveType'])
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
            $adjustment->load(['employee', 'dutyEmployee', 'leaveType']);
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

        $adjustment->refresh();
        $adjustment->load(['employee', 'dutyEmployee', 'leaveType']);

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
