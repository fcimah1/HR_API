<?php

namespace App\Services;

use App\Repository\Interface\EmployeeRepositoryInterface;
use App\DTOs\Employee\EmployeeFilterDTO;
use App\DTOs\Employee\EmployeeResponseDTO;
use App\DTOs\Employee\CreateEmployeeDTO;
use App\DTOs\Employee\UpdateEmployeeDTO;
use App\Models\User;
use App\Models\UserDetails;

use App\Services\SimplePermissionService;
use Exception;

class EmployeeService
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly SimplePermissionService $permissionService
    ) {}

    /**
     * Get active employees for target employee selection (who can have a leave)
     * Enforces hierarchy rules: Level X sees self and Level > X.
     * 
     * @param User $user Current requester
     * @param string|null $search
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

    /**
     * Get backup employees (duty alternatives) based on target employee's department.
     * Enforces that the requester has permission to view the target employee.
     *
     * @param User $requester
     * @param int $targetEmployeeId
     * @return array
     * @throws Exception
     */
    public function getBackupEmployees(User $requester, ?int $targetEmployeeId = null, ?string $search = null, ?int $employeeId = null): array
    {
        // 1. Determine target employee
        if ($targetEmployeeId === null) {
            $targetEmployeeId = $requester->user_id;
        }

        $targetEmployee = User::with('user_details')->find($targetEmployeeId);
        if (!$targetEmployee) {
            throw new Exception('الموظف غير موجود');
        }

        // 2. Permission check: Can requester view/act for target employee?
        // (Must be self or subordinate based on Hierarchy)
        if ($requester->user_id !== $targetEmployee->user_id && !$this->permissionService->canViewEmployeeRequests($requester, $targetEmployee)) {
            throw new Exception('ليس لديك صلاحية لإجراء هذا الطلب لهذا الموظف.');
        }

        // 3. Build query for backup candidates
        $query = User::query();

        // Applying "Same Department, Ignore Hierarchy" logic
        $this->permissionService->filterBackupEmployees($query, $targetEmployee);

        // Apply search and employeeId filters if provided
        if ($search) {
            $searchTerm = "%{$search}%";
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'LIKE', $searchTerm)
                    ->orWhere('last_name', 'LIKE', $searchTerm)
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$searchTerm]);
            });
        }

        if ($employeeId) {
            $query->where('user_id', $employeeId);
        }

        // 4. Execute and map
        return $query->with(['user_details.designation', 'user_details.department'])
            ->get()
            ->map(function ($user) {
                return [
                    'user_id' => $user->user_id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'department_id' => $user->user_details->department_id ?? null,
                    'department_name' => $user->user_details->department->department_name ?? 'N/A',
                    'designation_name' => $user->user_details->designation->designation_name ?? 'N/A',
                    'hierarchy_level' => $user->user_details->designation->hierarchy_level ?? null,
                ];
            })
            ->toArray();
    }

    /**
     * Get approval levels (approvers) for an employee.
     * Returns the configured approval chain with user details.
     *
     * @param User $requester The user making the request
     * @param int|null $targetEmployeeId Optional employee ID. If null, uses requester's ID.
     * @return array
     * @throws Exception
     */
    public function getApprovalLevels(User $requester, ?int $targetEmployeeId = null): array
    {
        // Default to requester if no target provided
        if ($targetEmployeeId === null) {
            $targetEmployeeId = $requester->user_id;
        }

        // Load target employee
        $targetEmployee = User::with(['user_details.designation'])->find($targetEmployeeId);

        if (!$targetEmployee) {
            throw new Exception('الموظف غير موجود');
        }

        // Permission check: Can requester view target employee?
        if (
            $requester->user_id !== $targetEmployee->user_id &&
            $requester->user_type !== 'company' &&
            !$this->permissionService->canViewEmployeeRequests($requester, $targetEmployee)
        ) {
            throw new Exception('ليس لديك صلاحية لعرض بيانات هذا الموظف');
        }

        // Get UserDetails for approval levels
        $userDetails = UserDetails::where('user_id', $targetEmployeeId)->first();

        if (!$userDetails) {
            return [
                'employee' => [
                    'user_id' => $targetEmployee->user_id,
                    'full_name' => trim($targetEmployee->first_name . ' ' . $targetEmployee->last_name),
                    'email' => $targetEmployee->email,
                ],
                'approval_levels' => [],
                'reporting_manager' => null,
            ];
        }

        // Build approval levels array
        $approvalLevels = [];

        // Level 1
        if ($userDetails->approval_level01) {
            $approver1 = User::with('user_details.designation')->find($userDetails->approval_level01);
            if ($approver1) {
                $approvalLevels[] = [
                    'level' => 1,
                    'user_id' => $approver1->user_id,
                    'full_name' => trim($approver1->first_name . ' ' . $approver1->last_name),
                    'email' => $approver1->email,
                    'designation' => $approver1->user_details?->designation?->designation_name,
                    'hierarchy_level' => $approver1->getHierarchyLevel(),
                ];
            }
        }

        // Level 2
        if ($userDetails->approval_level02) {
            $approver2 = User::with('user_details.designation')->find($userDetails->approval_level02);
            if ($approver2) {
                $approvalLevels[] = [
                    'level' => 2,
                    'user_id' => $approver2->user_id,
                    'full_name' => trim($approver2->first_name . ' ' . $approver2->last_name),
                    'email' => $approver2->email,
                    'designation' => $approver2->user_details?->designation?->designation_name,
                    'hierarchy_level' => $approver2->getHierarchyLevel(),
                ];
            }
        }

        // Level 3
        if ($userDetails->approval_level03) {
            $approver3 = User::with('user_details.designation')->find($userDetails->approval_level03);
            if ($approver3) {
                $approvalLevels[] = [
                    'level' => 3,
                    'user_id' => $approver3->user_id,
                    'full_name' => trim($approver3->first_name . ' ' . $approver3->last_name),
                    'email' => $approver3->email,
                    'designation' => $approver3->user_details?->designation?->designation_name,
                    'hierarchy_level' => $approver3->getHierarchyLevel(),
                ];
            }
        }

        // Reporting manager as fallback
        $reportingManager = null;
        if ($userDetails->reporting_manager) {
            $manager = User::with('user_details.designation')->find($userDetails->reporting_manager);
            if ($manager) {
                $reportingManager = [
                    'user_id' => $manager->user_id,
                    'full_name' => trim($manager->first_name . ' ' . $manager->last_name),
                    'email' => $manager->email,
                    'designation' => $manager->user_details?->designation?->designation_name,
                    'hierarchy_level' => $manager->getHierarchyLevel(),
                ];
            }
        }

        return [
            'employee' => [
                'user_id' => $targetEmployee->user_id,
                'full_name' => trim($targetEmployee->first_name . ' ' . $targetEmployee->last_name),
                'email' => $targetEmployee->email,
                'designation' => $targetEmployee->user_details?->designation?->designation_name,
                'hierarchy_level' => $targetEmployee->getHierarchyLevel(),
            ],
            'approval_levels' => $approvalLevels,
            'reporting_manager' => $reportingManager,
            'total_levels' => count($approvalLevels),
        ];
    }
}
