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
     * Create new employee
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
}
