<?php

namespace App\Repository\Interface;

use App\DTOs\Leave\LeaveApplicationFilterDTO;
use App\DTOs\Leave\CreateLeaveApplicationDTO;
use App\DTOs\Leave\UpdateLeaveApplicationDTO;
use App\DTOs\Leave\LeaveAdjustmentFilterDTO;
use App\DTOs\Leave\CreateLeaveAdjustmentDTO;
use App\Models\LeaveApplication;
use App\Models\LeaveAdjustment;
use App\DTOs\Leave\CreateLeaveTypeDTO;
use App\DTOs\Leave\UpdateLeaveAdjustmentDTO;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LeaveRepositoryInterface
{
    /**
     * Get paginated leave applications with filters
     */
    public function getPaginatedApplications(LeaveApplicationFilterDTO $filters): LengthAwarePaginator;

    /**
     * Create a new leave application
     */
    public function createApplication(CreateLeaveApplicationDTO $dto): LeaveApplication;

    /**
     * Find leave application by ID
     */
    public function findApplication(int $id): ?LeaveApplication;

    /**
     * Find leave application by ID for specific company
     */
    public function findApplicationInCompany(int $id, int $companyId): ?LeaveApplication;

    /**
     * Find leave application by ID for specific employee
     */
    public function findApplicationForEmployee(int $id, int $employeeId): ?LeaveApplication;

    /**
     * Update leave application
     */
    public function update_Application(LeaveApplication $application, UpdateLeaveApplicationDTO $dto): object;

    /**
     * Approve leave application
     */
    public function approveApplication(LeaveApplication $application, int $approvedBy, ?string $remarks = null): LeaveApplication;

    /**
     * Reject leave application
     */
    public function rejectApplication(LeaveApplication $application, int $rejectedBy, string $reason): LeaveApplication;

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
     * Get leave statistics for company
     */
    public function getLeaveStatistics(int $companyId): array;

    /**
     * Get active leave types for company
     */
    public function getActiveLeaveTypes(int $companyId): Collection;

    /**
     * Create leave type
     */
    public function createLeaveType(CreateLeaveTypeDTO $dto) :object;

    public function updateAdjustment(LeaveAdjustment $adjustment, UpdateLeaveAdjustmentDTO $dto): LeaveAdjustment;
    public function cancelAdjustment(LeaveAdjustment $adjustment, int $cancelledBy, string $reason): LeaveAdjustment;
    public function findAdjustmentForEmployee(int $id, int $employeeId): ?LeaveAdjustment;

    /**
     * Get total granted leave for an employee
     */
    public function getTotalGrantedLeave(int $employeeId, int $leaveTypeId, int $companyId): float;

    /**
     * Get total used leave for an employee
     */
    public function getTotalUsedLeave(int $employeeId, int $leaveTypeId, int $companyId): float;
}
