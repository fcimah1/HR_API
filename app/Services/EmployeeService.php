<?php

namespace App\Services;

use App\Repository\Interface\EmployeeRepositoryInterface;
use App\DTOs\Employee\EmployeeFilterDTO;
use App\DTOs\Employee\EmployeeResponseDTO;
use App\DTOs\Employee\CreateEmployeeDTO;
use App\DTOs\Employee\UpdateEmployeeDTO;
use App\Models\User;
use App\Models\UserDetails;

class EmployeeService
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository
    ) {}

    /**
     * Get paginated employees with filters
     */
    public function getPaginatedEmployees(EmployeeFilterDTO $filters): array
    {
        $employees = $this->employeeRepository->getPaginatedEmployees($filters);

        $employeeDTOs = collect($employees->items())->map(function ($employee) {
            return EmployeeResponseDTO::fromModel($employee);
        });

        return [
            'data' => $employeeDTOs->map(fn($dto) => $dto->toArray())->toArray(),
            'pagination' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
                'from' => $employees->firstItem(),
                'to' => $employees->lastItem(),
                'has_more_pages' => $employees->hasMorePages(),
            ]
        ];
    }

    /**
     * Get employee by ID
     */
    public function getEmployeeById(int $employeeId, int $companyId): ?EmployeeResponseDTO
    {
        $employee = $this->employeeRepository->findEmployeeInCompany($employeeId, $companyId);

        return $employee ? EmployeeResponseDTO::fromModel($employee) : null;
    }

    /**
     * Get employee statistics
     */
    public function getEmployeeStats(int $companyId): array
    {
        return $this->employeeRepository->getEmployeeStats($companyId);
    }

    /**
     * Check if user can view employees
     */
    public function canViewEmployees(User $user): bool
    {
        $allowedRoles = ['company', 'super_user', 'admin', 'hr', 'manager'];
        return in_array(strtolower($user->user_type), $allowedRoles);
    }

    /**
     * Check if user can view specific employee
     */
    public function canViewEmployee(User $user, int $employeeId): bool
    {
        // Company, Super User, Admin and HR can view all employees in their company
        if (in_array(strtolower($user->user_type), ['company', 'super_user', 'admin', 'hr'])) {
            return $this->employeeRepository->employeeExistsInCompany($employeeId, $user->company_id);
        }

        // Managers can view employees in their company
        if (strtolower($user->user_type) === 'manager') {
            return $this->employeeRepository->employeeExistsInCompany($employeeId, $user->company_id);
        }

        // Regular employees can only view themselves
        return $user->user_id === $employeeId;
    }

    /**
     * Get employees by type
     */
    public function getEmployeesByType(int $companyId, string $userType): array
    {
        $employees = $this->employeeRepository->getEmployeesByType($companyId, $userType);

        return $employees->map(function ($employee) {
            return EmployeeResponseDTO::fromModel($employee)->toArray();
        })->toArray();
    }

    /**
     * Search employees
     */
    public function searchEmployees(int $companyId, string $searchTerm): array
    {
        $employees = $this->employeeRepository->searchEmployees($companyId, $searchTerm);

        return $employees->map(function ($employee) {
            return EmployeeResponseDTO::fromModel($employee)->toArray();
        })->toArray();
    }

    /**
     * Create new employee
     */
    public function createEmployee(CreateEmployeeDTO $employeeData): EmployeeResponseDTO
    {
        $user = $this->employeeRepository->createEmployee($employeeData);
        return EmployeeResponseDTO::fromModel($user);
    }

    /**
     * Update employee
     */
    public function updateEmployee(UpdateEmployeeDTO $employeeData): bool
    {
        return $this->employeeRepository->updateEmployee($employeeData);
    }

    /**
     * Delete employee
     */
    public function deleteEmployee(int $employeeId, int $companyId, User $currentUser): bool
    {
        // Check permissions
        if (!$this->canManageEmployee($currentUser, $employeeId)) {
            return false;
        }

        return $this->employeeRepository->deleteEmployee($employeeId, $companyId);
    }

    /**
     * Get employee with full details
     */
    public function getEmployeeWithDetails(int $employeeId, int $companyId): ?EmployeeResponseDTO
    {
        $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $companyId);

        return $employee ? EmployeeResponseDTO::fromModel($employee) : null;
    }

    /**
     * Check if user can manage (create/update/delete) employees
     */
    public function canManageEmployees(User $user): bool
    {
        $allowedRoles = ['company', 'super_user', 'admin', 'hr'];
        return in_array(strtolower($user->user_type), $allowedRoles);
    }

    /**
     * Check if user can manage specific employee
     */
    public function canManageEmployee(User $user, int $employeeId): bool
    {
        // Company, Super User, Admin and HR can manage all employees in their company
        if (in_array(strtolower($user->user_type), ['company', 'super_user', 'admin', 'hr'])) {
            return $this->employeeRepository->employeeExistsInCompany($employeeId, $user->company_id);
        }

        // Users cannot manage other employees
        return false;
    }




    /**
     * Get active employees for duty employee selection with optional filters
     * Returns list of active employees in the specified company and optionally same department
     * 
     * @param int $companyId
     * @param string|null $search Optional search term to filter by name, email, or company name
     * @param int|null $employeeId Optional employee ID to filter by specific employee
     * @param int|null $departmentId Optional department ID to filter by same department
     * @return array
     */
    public function getEmployeesForDutyEmployee(int $companyId, ?string $search = null, ?int $employeeId = null, ?int $departmentId = null): array
    {

        $employees = $this->employeeRepository->getDutyEmployee(
            $companyId,
            $search,
            $employeeId,
            $departmentId
        );

        return $employees;
    }

    /**
     * Get employees who can receive notifications
     * Based on CanNotifyUser rules:
     * 1- user_type = company for same company
     * 2- hierarchy_level = 1 (top level)
     * 3- Higher hierarchy level (regardless of department)
     *
     * @param int $companyId
     * @param int $currentUserId
     * @param int|null $currentHierarchyLevel
     * @param int|null $currentDepartmentId
     * @param string|null $search
     * @return array
     */
    public function getEmployeesForNotify(int $companyId, int $currentUserId, ?int $currentHierarchyLevel = null, ?int $currentDepartmentId = null, ?string $search = null): array
    {

        $employeesArray = $this->employeeRepository->getEmployeesForNotify(
            $companyId,
            $currentUserId,
            $currentHierarchyLevel,
            $currentDepartmentId,
            $search
        );

        // Convert to collection of User models for filtering with model methods
        $employees = collect($employeesArray)->map(function ($employeeData) {
            return User::with('user_details.designation')->find($employeeData['user_id']);
        })->filter(); // Remove nulls

        // Filter employees based on CanNotifyUser rules
        $filteredEmployees = $employees->filter(function ($employee) use ($currentHierarchyLevel, $currentDepartmentId) {
            // 1- If user_type = company - allowed
            if ($employee->user_type === 'company') {
                return true;
            }

            // Get hierarchy_level
            $employeeHierarchyLevel = $employee->getHierarchyLevel();

            // 2- If hierarchy_level = 1 - allowed
            if ($employeeHierarchyLevel === 1) {
                return true;
            }

            // 3- Higher hierarchy level (regardless of department)
            // Note: Department check removed to match CanNotifyUser validation rule
            if (
                $employeeHierarchyLevel !== null &&
                $currentHierarchyLevel !== null &&
                $employeeHierarchyLevel < $currentHierarchyLevel
            ) {
                return true;
            }

            return false;
        });

        // Transform data
        return $filteredEmployees->map(function ($employee) {
            return [
                'user_id' => $employee->user_id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'full_name' => trim($employee->first_name . ' ' . $employee->last_name),
                'email' => $employee->email,
                'user_type' => $employee->user_type,
                'hierarchy_level' => $employee->getHierarchyLevel(),
                'department_id' => $employee->user_details?->department_id,
            ];
        })->values()->toArray();
    }

    /**
     * Get user with hierarchy information (level and department)
     *
     * @param int $userId
     * @return array|null
     */
    public function getUserWithHierarchyInfo(int $userId): ?array
    {
        $userData = $this->employeeRepository->getUserWithHierarchyInfo($userId);

        if (!$userData) {
            return null;
        }

        // Fetch the actual User model to call methods like getHierarchyLevel()
        $user = User::with('user_details.designation')->find($userId);

        if (!$user) {
            return null;
        }

        return [
            'user_id' => $user->user_id,
            'hierarchy_level' => $user->getHierarchyLevel(),
            'department_id' => $user->user_details?->department_id,
        ];
    }
}
