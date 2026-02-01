<?php

namespace App\Repository\Interface;

use App\DTOs\Employee\EmployeeFilterDTO;
use App\DTOs\Employee\CreateEmployeeDTO;
use App\DTOs\Employee\UpdateEmployeeDTO;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface EmployeeRepositoryInterface
{
    /**
     * Get paginated employees with filters
     */
    public function getPaginatedEmployees(EmployeeFilterDTO $filters): LengthAwarePaginator;

    /**
     * Find employee by ID within company
     */
    public function findEmployeeInCompany(int $employeeId, int $companyId): ?User;

    /**
     * Get employee statistics for company
     */
    public function getEmployeeStats(int $companyId): array;

    /**
     * Get all employees in company
     */
    public function getAllEmployeesInCompany(int $companyId): Collection;

    /**
     * Check if employee exists in company
     */
    public function employeeExistsInCompany(int $employeeId, int $companyId): bool;

    /**
     * Get employees by user type
     */
    public function getEmployeesByType(int $companyId, string $userType): Collection;

    /**
     * Get active employees count
     */
    public function getActiveEmployeesCount(int $companyId): int;

    /**
     * Search employees by term
     */
    public function searchEmployees(int $companyId, string $searchTerm): Collection;

    /**
     * Create employee
     */
    public function createEmployee(CreateEmployeeDTO $employeeData): User;

    /**
     * Update employee
     */
    public function updateEmployee(UpdateEmployeeDTO $employeeData): bool;

    /**
     * Delete employee
     */
    public function deleteEmployee(int $employeeId, int $companyId): bool;

    /**
     * Get employee with details
     */
    public function getEmployeeWithDetails(int $employeeId, int $companyId): ?User;


    /**
     * Get active duty employees with optional search
     *
     * @param int $id Company ID
     * @param string|null $search Optional search term to filter users by name, email, or company name
     * @param int|null $employeeId Optional employee ID to filter by specific employee
     * @param int|null $departmentId Optional department ID to filter by same department
     * @return array
     */
    public function getDutyEmployee(int $id, ?string $search = null, ?int $employeeId = null, ?int $departmentId = null, ?int $excludeEmployeeId = null): array;

    /**
     * Get employees for notification
     *
     * @param int $companyId
     * @param int $currentUserId
     * @param int|null $currentHierarchyLevel
     * @param int|null $currentDepartmentId
     * @param string|null $search
     * @return array
     */
    public function getEmployeesForNotify(int $companyId, int $currentUserId, ?int $currentHierarchyLevel = null, ?int $currentDepartmentId = null, ?string $search = null): array;

    /**
     * Get user with hierarchy information (level and department)
     *
     * @param int $userId
     * @return array|null
     */
    public function getUserWithHierarchyInfo(int $userId): ?array;

    /**
     * Get attendance records for an employee
     */
    public function getAttendanceRecords(int $employeeId, string $fromDate, string $toDate);

    /**
     * Get approved leaves for an employee within a period
     */
    public function getApprovedLeaves(int $employeeId, string $fromDate, string $toDate);

    /**
     * Get company holidays within a period
     */
    public function getHolidays(int $companyId, string $fromDate, string $toDate);

    /**
     * Get leave types constants for a company
     */
    public function getLeaveTypes(int $companyId);

    /**
     * Get leave applications for an employee in a specific year and status
     */
    public function getLeaveApplicationsByYear(int $employeeId, int $companyId, int $year, ?int $status = null);

    /**
     * Get leave adjustments for an employee in a specific year
     */
    public function getLeaveAdjustmentsByYear(int $employeeId, int $companyId, int $year);

    /**
     * Get recent leaves for an employee
     */
    public function getRecentLeaves(int $employeeId, int $companyId, int $limit = 5);

    /**
     * Generate next automatic employee ID number for a company
     */
    public function generateNextEmployeeIdnum(int $companyId): string;

    /**
     * Get integrated stats for dashboard
     */
    public function getAdvancedStats(int $companyId, array $options = []): array;

    /**
     * Update employee password
     */
    public function updateEmployeePassword(int $employeeId, int $companyId, string $hashedPassword): bool;

    /**
     * Update employee profile image
     */
    public function updateEmployeeProfileImage(int $employeeId, string $imageUrl): bool;

    /**
     * Insert employee document
     */
    public function insertEmployeeDocument(array $documentData): int;

    /**
     * Update employee profile info (username, email)
     */
    public function updateEmployeeProfileInfo(int $employeeId, int $companyId, array $profileData): bool;

    /**
     * Update employee CV data
     */
    public function updateEmployeeCV(int $employeeId, array $cvData): bool;

    /**
     * Update employee social links
     */
    public function updateEmployeeSocialLinks(int $employeeId, array $socialData): bool;

    /**
     * Update or insert employee bank info
     */
    public function updateEmployeeBankInfo(int $employeeId, array $bankData): bool;

    /**
     * Add employee family data
     */
    public function addEmployeeFamilyData(int $employeeId, array $familyData): bool;

    /**
     * Delete employee family data
     */
    public function deleteEmployeeFamilyData(int $contactId): bool;

    /**
     * Get employee documents with optional search
     */
    public function getEmployeeDocuments(int $employeeId, ?string $search = null): \Illuminate\Support\Collection;

    /**
     * Update employee basic information
     */
    public function updateEmployeeBasicInfo(int $employeeId, array $userData, array $detailsData): bool;

    /**
     * Get employee contract data
     */
    public function getEmployeeContractData(int $employeeId): array;

    /**
     * Update employee contract data
     */
    public function updateEmployeeContractData(int $employeeId, array $data): bool;

    /**
     * Get available contract options for a company
     */
    public function getContractOptions(int $companyId): array;

    /**
     * Add contract components
     */
    public function addAllowance(int $employeeId, array $data): int;
    public function addCommission(int $employeeId, array $data): int;
    public function addStatutoryDeduction(int $employeeId, array $data): int;
    public function addOtherPayment(int $employeeId, array $data): int;

    /**
     * Contract components existence check
     */
    public function allowanceExists(int $employeeId, string $payTitle): bool;
    public function commissionExists(int $employeeId, string $payTitle): bool;
    public function statutoryDeductionExists(int $employeeId, string $payTitle): bool;
    public function otherPaymentExists(int $employeeId, string $payTitle): bool;

    /**
     * Update contract components
     */
    public function updateAllowance(int $id, array $data): bool;
    public function updateCommission(int $id, array $data): bool;
    public function updateStatutoryDeduction(int $id, array $data): bool;
    public function updateOtherPayment(int $id, array $data): bool;

    /**
     * Delete contract components
     */
    public function deleteAllowance(int $id): bool;
    public function deleteCommission(int $id): bool;
    public function deleteStatutoryDeduction(int $id): bool;
    public function deleteOtherPayment(int $id): bool;

    /**
     * Get contract components by ID
     */
    public function getAllowanceById(int $id): ?object;
    public function getCommissionById(int $id): ?object;
    public function getStatutoryDeductionById(int $id): ?object;
    public function getOtherPaymentById(int $id): ?object;

    /**
     * Get contract components with optional search
     */
    public function getAllowances(int $employeeId, ?string $search = null): array;
    public function getCommissions(int $employeeId, ?string $search = null): array;
    public function getStatutoryDeductions(int $employeeId, ?string $search = null): array;
    public function getOtherPayments(int $employeeId, ?string $search = null): array;

    /**
     * Get employee count grouped by country
     */
    public function getEmployeeCountByCountry(int $companyId): array;
}
