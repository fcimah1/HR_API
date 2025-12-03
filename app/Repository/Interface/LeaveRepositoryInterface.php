<?php

namespace App\Repository\Interface;

use App\DTOs\Leave\CreateHourlyLeaveDTO;
use App\DTOs\Leave\LeaveApplicationFilterDTO;
use App\DTOs\Leave\CreateLeaveApplicationDTO;
use App\DTOs\Leave\UpdateLeaveApplicationDTO;
use App\Models\LeaveApplication;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LeaveRepositoryInterface
{
    /**
     * Get paginated leave applications with filters
     */
    public function getPaginatedApplications(LeaveApplicationFilterDTO $filters, User $user): array;

    /**
     * Create a new leave application from DTO
     */
    public function createApplication(CreateLeaveApplicationDTO $dto): LeaveApplication;

    /**
     * Create a new leave application from hourly DTO
     * 
     * @param CreateHourlyLeaveDTO $dto
     * @return LeaveApplication
     */
    public function createApplicationFromHourly(CreateHourlyLeaveDTO $dto): LeaveApplication;

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
     * Get active employees for duty employee selection with optional filters
     * 
     * @param int $id Company ID
     * @param string|null $search Optional search term to filter by name, email, or company name
     * @param int|null $employeeId Optional employee ID to filter by specific employee
     * @return array
     */
    public function getDutyEmployee(int $id, ?string $search = null, ?int $employeeId = null): array;

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

    /**
     * Get monthly granted hours for a leave type
     * Returns array with month number as key and granted hours as value
     * 
     * @param int $employeeId
     * @param int $leaveTypeId
     * @param int $companyId
     * @return array [1 => 13.33, 2 => 13.33, ...]
     */
    public function getMonthlyGrantedHours(int $employeeId, int $leaveTypeId, int $companyId): array;

    /**
     * Get monthly used hours for a leave type
     * Returns array with month number as key and used hours as value
     * 
     * @param int $employeeId
     * @param int $leaveTypeId
     * @param int $companyId
     * @param int $year
     * @return array [1 => 8.0, 2 => 0.0, ...]
     */
    public function getMonthlyUsedHours(int $employeeId, int $leaveTypeId, int $companyId, int $year): array;
}
