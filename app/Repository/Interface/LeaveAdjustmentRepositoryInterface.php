<?php

namespace App\Repository\Interface;

use App\DTOs\LeaveAdjustment\CreateLeaveAdjustmentDTO;
use App\DTOs\LeaveAdjustment\UpdateLeaveAdjustmentDTO;
use App\DTOs\LeaveAdjustment\LeaveAdjustmentFilterDTO;
use App\Models\LeaveAdjustment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LeaveAdjustmentRepositoryInterface
{
    /**
     * Get paginated leave adjustments with filters
     */
    public function getPaginatedAdjustments(LeaveAdjustmentFilterDTO $filters): LengthAwarePaginator;

    /**
     * Create a new leave adjustment
     */
    public function createAdjust(CreateLeaveAdjustmentDTO $dto): object;

    /**
     * Find leave adjustment by ID
     */
    public function findAdjustment(int $id): ?LeaveAdjustment;

    /**
     * Find leave adjustment by ID for specific company
     */
    public function findAdjustmentInCompany(int $id, int $companyId): ?LeaveAdjustment;

    /**
     * Approve leave adjustment
     */
    public function approveAdjustment(LeaveAdjustment $adjustment, int $approvedBy): LeaveAdjustment;

    /**
     * Reject leave adjustment
     */
    public function rejectAdjustment(LeaveAdjustment $adjustment, int $rejectedBy, string $reason): LeaveAdjustment;

    /**
     * Update leave adjustment
     */
    public function updateAdjustment(LeaveAdjustment $adjustment, UpdateLeaveAdjustmentDTO $dto): LeaveAdjustment;
    
    /**
     * Cancel leave adjustment
     */
    public function cancelAdjustment(LeaveAdjustment $adjustment, int $cancelledBy, string $reason): LeaveAdjustment;
    
    /**
     * Find leave adjustment by ID for specific employee
     */
    public function findAdjustmentForEmployee(int $id, int $employeeId): ?LeaveAdjustment;

    /**
     * Get total approved adjustment hours for an employee (in hours)
     */
    public function getTotalAdjustmentHours(int $employeeId, int $leaveTypeId, int $companyId): float;
}
