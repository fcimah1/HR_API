<?php

namespace App\Repository\Interface;

use App\DTOs\Leave\LeaveApplicationFilterDTO;
use App\DTOs\Leave\CreateLeaveApplicationDTO;
use App\DTOs\Leave\UpdateLeaveApplicationDTO;
use App\Models\LeaveApplication;
use App\DTOs\Leave\CreateLeaveTypeDTO;
use App\DTOs\Leave\UpdateLeaveTypeDTO;
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

    /**
     * Update leave type
     */
    public function updateLeaveType(UpdateLeaveTypeDTO $dto) :object;

    /**
     * Delete leave type
     */
    public function deleteLeaveType(int $leaveTypeId, int $companyId): bool;

    /**
     * Get total granted leave for an employee
     */
    public function getTotalGrantedLeave(int $employeeId, int $leaveTypeId, int $companyId): float;

    /**
     * Get total used leave for an employee
     */
    public function getTotalUsedLeave(int $employeeId, int $leaveTypeId, int $companyId): float;

    /**
     * Get total pending leave hours for an employee (in hours)
     */
    public function getPendingLeaveHours(int $employeeId, int $leaveTypeId, int $companyId): float;

    
    /**
     * Get total approved adjustment hours for an employee (in hours)
     */
    public function getTotalAdjustmentHours(int $employeeId, int $leaveTypeId, int $companyId): float;

}
