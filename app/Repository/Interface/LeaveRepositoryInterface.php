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

    // ==========================================
    // Fiscal Year Aware Methods for Leave Report
    // ==========================================

    /**
     * Get total used leave for an employee in a specific fiscal period (in hours)
     */
    public function getUsedLeaveInPeriod(int $employeeId, int $leaveTypeId, int $companyId, string $startDate, string $endDate): float;

    /**
     * Get total pending leave for an employee in a specific fiscal period (in hours)
     */
    public function getPendingLeaveInPeriod(int $employeeId, int $leaveTypeId, int $companyId, string $startDate, string $endDate): float;

    /**
     * Get total adjustments for an employee in a specific fiscal period (in hours)
     */
    public function getAdjustmentsInPeriod(int $employeeId, int $leaveTypeId, int $companyId, string $startDate, string $endDate): float;

    /**
     * Get list of approved leave dates for an employee in a specific period
     */
    public function getApprovedLeaveDates(int $employeeId, int $leaveTypeId, int $companyId, string $startDate, string $endDate): string;
}
