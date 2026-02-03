<?php

namespace App\Services;

use App\Models\User;
use App\DTOs\Employee\EmployeeFilterDTO;
use App\DTOs\Employee\CreateEmployeeDTO;
use App\DTOs\Employee\UpdateEmployeeDTO;
use App\Services\FileUploadService;
use App\Enums\ExperienceLevel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Attendance;
use App\Models\ErpConstant;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\OfficeShift;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;

/**
 * Employee Management Service
 * 
 * Handles employee operations with hierarchy and permission checks
 * Uses existing SimplePermissionService for all permission logic
 */
class EmployeeManagementService
{
    public function __construct(
        private SimplePermissionService $permissionService,
        private \App\Repository\Interface\EmployeeRepositoryInterface $employeeRepository,
        private FileUploadService $fileUploadService
    ) {}

    /**
     * Get employees list with filters and permissions
     * 
     * @param User $user Current user requesting the data
     * @param EmployeeFilterDTO $filters Filter parameters
     * @return LengthAwarePaginator
     */
    public function getEmployeesList(User $user, EmployeeFilterDTO $filters): LengthAwarePaginator
    {
        try {
            // Get base query with company filtering
            $query = $this->buildEmployeesQuery($user, $filters);

            // Apply additional filters
            $this->applyFilters($query, $filters);

            // Apply search
            if ($filters->search) {
                $this->applySearch($query, $filters->search);
            }

            // Apply sorting
            $this->applySorting($query, $filters->sort_by ?? null, $filters->sort_direction ?? 'asc');

            return $query->paginate($filters->limit, ['*'], 'page', $filters->page);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getEmployeesList failed', [
                'user_id' => $user->user_id,
                'filters' => $filters->toArray(),
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء جلب قائمة الموظفين'
            ]);

            // Return empty paginator on error
            return new LengthAwarePaginator([], 0, $filters->limit, $filters->page);
        }
    }

    /**
     * Get employee details with permission check
     * 
     * @param User $user Current user requesting the data
     * @param int $employeeId Target employee ID
     * @return User|null
     */
    public function getEmployeeDetails(User $user, int $employeeId): ?User
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);

            if (!$employee) {
                Log::error('EmployeeManagementService::getEmployeeDetails failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'لا يوجد موظف بهذا الرقم'
                ]);
                throw new \Exception(message: 'لا يوجد موظف بهذا الرقم', code: 404);
            }

            // Check if user can access this employee
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::getEmployeeDetails failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحيه لعرض طلبات هذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك صلاحيه لعرض طلبات هذا الموظف', code: 403);
            }

            Log::info('EmployeeManagementService::getEmployeeDetails', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'message' => 'تم جلب معلومات الموظف'
            ]);
            return $employee;
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getEmployeeDetails failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء جلب معلومات الموظف'
            ]);
            throw new \Exception(message: 'حدث خطأ أثناء جلب معلومات الموظف', code: 500);
        }
    }
    /**
     * Get eligible approvers for a specific employee based on hierarchy.
     * Rule: Approvers must have a lower hierarchy_level (higher rank) than the target employee.
     * 
     * @param User $user Current user requesting the data
     * @param int $targetEmployeeId The employee for whom we are selecting an approver
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getEligibleApprovers(User $user, int $targetEmployeeId)
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // Get target employee with designation details from repository
            $targetEmployee = $this->employeeRepository->getEmployeeWithDetails($targetEmployeeId, $effectiveCompanyId);

            if (!$targetEmployee) {
                Log::error('EmployeeManagementService::getEligibleApprovers failed', [
                    'user_id' => $user->user_id,
                    'target_employee_id' => $targetEmployeeId,
                    'message' => 'الموظف المستهدف غير موجود في شركتك أو غير نشط.'
                ]);
                throw new \Exception('الموظف المستهدف غير موجود في شركتك أو غير نشط.', 404);
            }

            // Check if user can access this employee
            if (!$this->permissionService->canViewEmployeeRequests($user, $targetEmployee)) {
                // If it's a peer/superior or restricted, provide a clear reason
                $managerLevel = $this->permissionService->getUserHierarchyLevel($user);
                $employeeLevel = $this->permissionService->getUserHierarchyLevel($targetEmployee);

                if ($managerLevel !== null && $employeeLevel !== null && $managerLevel >= $employeeLevel) {
                    Log::error('EmployeeManagementService::getEligibleApprovers failed', [
                        'user_id' => $user->user_id,
                        'target_employee_id' => $targetEmployeeId,
                        'message' => 'ليس لديك صلاحية. يجب أن تكون في مستوى هرمي أعلى من الموظف المستهدف.'
                    ]);
                    throw new \Exception('ليس لديك صلاحية. يجب أن تكون في مستوى هرمي أعلى من الموظف المستهدف.', 403);
                }

                Log::error('EmployeeManagementService::getEligibleApprovers failed', [
                    'user_id' => $user->user_id,
                    'target_employee_id' => $targetEmployeeId,
                    'message' => 'ليس لديك صلاحية للعرض بسبب قيود إدارية على القسم أو الفرع الخاص بهذا الموظف.'
                ]);
                throw new \Exception('ليس لديك صلاحية للعرض بسبب قيود إدارية على القسم أو الفرع الخاص بهذا الموظف.', 403);
            }

            if (!$targetEmployee->user_details || !$targetEmployee->user_details->designation) {
                throw new \Exception('بيانات الرتبة الوظيفية للموظف المستهدف غير مكتملة.', 422);
            }

            $excludeIds = [$targetEmployeeId];

            $targetLevel = (int) $targetEmployee->user_details->designation->hierarchy_level;

            // Fetch potential approvers from repository
            $approvers = $this->employeeRepository->getEligibleApprovers(
                $effectiveCompanyId,
                $targetLevel,
                $excludeIds
            );

            if ($approvers->isEmpty()) {
                Log::error('EmployeeManagementService::getEligibleApprovers failed', [
                    'user_id' => $user->user_id,
                    'target_employee_id' => $targetEmployeeId,
                    'message' => 'لا يوجد موظفون برتبة أعلى من الموظف المستهدف حالياً في الشركة.'
                ]);
                throw new \Exception('لا يوجد موظفون برتبة أعلى من الموظف المستهدف حالياً في الشركة.', 404);
            }

            Log::info('EmployeeManagementService::getEligibleApprovers success', [
                'user_id' => $user->user_id,
                'target_employee_id' => $targetEmployeeId,
                'approvers_count' => $approvers->count(),
                'message' => 'تم استرجاع قائمة المعتمدين المؤهلين بنجاح'
            ]);

            return $approvers;
        } catch (\Exception $e) {
            // Re-throw if it's already a logical exception with a message we want to show
            if (in_array($e->getCode(), [403, 404, 422])) {
                throw $e;
            }

            Log::error('EmployeeManagementService::getEligibleApprovers system error', [
                'user_id' => $user->user_id,
                'target_employee_id' => $targetEmployeeId,
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ فني أثناء جلب قائمة المعتمدين مؤهلين.'
            ]);

            throw new \Exception('حدث خطأ فني أثناء جلب قائمة المعتمدين مؤهلين.', 500);
        }
    }

    /**
     * Update employee approvers
     * 
     * @param User $user Current user taking action
     * @param int $targetEmployeeId Target employee ID
     * @param array $approvers Associative array of approvers (e.g. ['approval_level01' => 10])
     * @return bool
     */
    public function updateEmployeeApprovers(User $user, int $targetEmployeeId, array $approvers): bool
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // Get target employee with details
            $targetEmployee = $this->employeeRepository->getEmployeeWithDetails($targetEmployeeId, $effectiveCompanyId);

            if (!$targetEmployee) {
                Log::error('EmployeeManagementService::updateEmployeeApprovers error', [
                    'user_id' => $user->user_id,
                    'target_employee_id' => $targetEmployeeId,
                    'error' => 'الموظف المستهدف غير موجود في شركتك أو غير نشط.',
                    'message' => 'الموظف المستهدف غير موجود في شركتك أو غير نشط.'
                ]);
                throw new \Exception('الموظف المستهدف غير موجود في شركتك أو غير نشط.', 404);
            }

            // Check if user can access this employee
            if (!$this->permissionService->canViewEmployeeRequests($user, $targetEmployee)) {
                $managerLevel = $this->permissionService->getUserHierarchyLevel($user);
                $employeeLevel = $this->permissionService->getUserHierarchyLevel($targetEmployee);

                if ($managerLevel !== null && $employeeLevel !== null && $managerLevel >= $employeeLevel) {
                    Log::error('EmployeeManagementService::updateEmployeeApprovers error', [
                        'user_id' => $user->user_id,
                        'target_employee_id' => $targetEmployeeId,
                        'error' => 'ليس لديك صلاحية لتعديل بيانات هذا الموظف. يجب أن تكون في مستوى أعلى.',
                        'message' => 'ليس لديك صلاحية لتعديل بيانات هذا الموظف. يجب أن تكون في مستوى أعلى.'
                    ]);
                    throw new \Exception('ليس لديك صلاحية لتعديل بيانات هذا الموظف. يجب أن تكون في مستوى أعلى.', 403);
                }

                Log::error('EmployeeManagementService::updateEmployeeApprovers error', [
                    'user_id' => $user->user_id,
                    'target_employee_id' => $targetEmployeeId,
                    'error' => 'ليس لديك صلاحية للوصول لهذا الموظف بسبب قيود إدارية.',
                    'message' => 'ليس لديك صلاحية للوصول لهذا الموظف بسبب قيود إدارية.'
                ]);
                throw new \Exception('ليس لديك صلاحية للوصول لهذا الموظف بسبب قيود إدارية.', 403);
            }

            if (!$targetEmployee->user_details) {
                Log::error('EmployeeManagementService::updateEmployeeApprovers error', [
                    'user_id' => $user->user_id,
                    'target_employee_id' => $targetEmployeeId,
                    'error' => 'بيانات تفاصيل الموظف غير مكتملة في النظام.',
                    'message' => 'بيانات تفاصيل الموظف غير مكتملة في النظام.'
                ]);
                throw new \Exception('بيانات تفاصيل الموظف غير مكتملة في النظام.', 422);
            }

            // Verify approvers eligibility
            $targetLevel = (int) ($targetEmployee->user_details->designation->hierarchy_level ?? 5);

            $updateData = [];
            foreach (['approval_level01', 'approval_level02', 'approval_level03'] as $levelKey) {
                if (array_key_exists($levelKey, $approvers)) {
                    $approverId = $approvers[$levelKey];

                    if ($approverId) {
                        $approver = User::with('user_details.designation')->find($approverId);
                        if (!$approver || $approver->company_id != $effectiveCompanyId) {
                            throw new \Exception("المعتمد المختار في {$levelKey} غير موجود أو يتبع شركة أخرى.", 422);
                        }

                        $approverLevel = $approver->getHierarchyLevel();
                        if ($approverLevel === null || $approverLevel >= $targetLevel) {
                            throw new \Exception("المعتمد في {$levelKey} يجب أن يكون في مستوى هرمي أعلى من الموظف المستهدف.", 422);
                        }
                    }

                    $updateData[$levelKey] = $approverId;
                }
            }

            if (empty($updateData)) {
                Log::error('EmployeeManagementService::updateEmployeeApprovers error', [
                    'user_id' => $user->user_id,
                    'target_employee_id' => $targetEmployeeId,
                    'error' => 'لا توجد بيانات صالحة للتحديث',
                    'message' => 'لا توجد بيانات صالحة للتحديث'
                ]);
                throw new \Exception('لا توجد بيانات صالحة للتحديث.', 422);
            }

            Log::info('EmployeeManagementService::updateEmployeeApprovers success', [
                'user_id' => $user->user_id,
                'target_employee_id' => $targetEmployeeId,
                'update_data' => $updateData,
                'message' => 'تم تحديث بيانات المعتمدين بنجاح'
            ]);

            // Update using repository
            $success = $this->employeeRepository->updateUserDetails($targetEmployeeId, $updateData);

            if (!$success) {
                Log::error('EmployeeManagementService::updateEmployeeApprovers error', [
                    'user_id' => $user->user_id,
                    'target_employee_id' => $targetEmployeeId,
                    'message' => 'فشل تحديث بيانات المعتمدين في قاعدة البيانات.'
                ]);
                throw new \Exception('فشل تحديث بيانات المعتمدين في قاعدة البيانات.', 500);
            }

            return true;
        } catch (\Exception $e) {
            if (in_array($e->getCode(), [403, 404, 422])) {
                throw $e;
            }

            Log::error('EmployeeManagementService::updateEmployeeApprovers error', [
                'user_id' => $user->user_id,
                'target_employee_id' => $targetEmployeeId,
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ فني أثناء تحديث المعتمدين'
            ]);

            throw new \Exception('حدث خطأ فني أثناء تحديث المعتمدين: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create new employee with permission check
     * 
     * @param User $user Current user creating the employee
     * @param CreateEmployeeDTO $data Employee data
     * @return User|null
     */
    public function createEmployee(User $user, CreateEmployeeDTO $data): ?User
    {
        try {

            // Check company access
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            // Handle Automatic Employee ID generation if not provided
            $employeeId = $this->employeeRepository->generateNextEmployeeIdnum($companyId);


            // Encrypt password before creating
            $hashedPassword = bcrypt($data->password);

            // Create using repository - map DTO to Repository DTO with all enriched info
            $dataForRepo = new CreateEmployeeDTO(
                first_name: $data->first_name,
                last_name: $data->last_name,
                company_id: $companyId,
                company_name: $data->company_name,
                username: $data->username,
                email: $data->email,
                password: $hashedPassword,
                department_id: $data->department_id,
                designation_id: $data->designation_id,
                contact_number: $data->contact_number,
                gender: $data->gender,
                basic_salary: $data->basic_salary,
                currency_id: $data->currency_id,
                user_role_id: $data->user_role_id ?? 1,
                reporting_manager: $data->reporting_manager,
                office_shift_id: $data->office_shift_id,
                employee_id: $employeeId,
            );

            $employee = $this->employeeRepository->createEmployee($dataForRepo);

            // Repository takes care of UserDetails
            // ... Logic moved to Repository

            // Clear permissions cache for the new employee
            $this->permissionService->clearUserPermissionsCache($employee->user_id);

            return $employee->load(['user_details.designation', 'user_details.department']);
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('EmployeeManagementService::createEmployee failed', [
                'user_id' => $user->user_id,
                'data' => $data->toArray(),
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء إنشاء الموظف'
            ]);

            return null;
        }
    }

    /**
     * Update employee with permission check
     * 
     * @param User $user Current user updating the employee
     * @param int $employeeId Target employee ID
     * @param UpdateEmployeeDTO $data Update data
     * @return User|null
     */
    public function updateEmployee(User $user, int $employeeId, UpdateEmployeeDTO $data): ?User
    {
        try {
            $employee = User::with('user_details')->find($employeeId);

            if (!$employee) {
                Log::error('EmployeeManagementService::updateEmployee failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'لا يوجد موظف بهذا الرقم'
                ]);
                return null;
            }

            DB::beginTransaction();

            // Update user record
            $employee->update(array_filter([
                'first_name' => $data->first_name,
                'last_name' => $data->last_name,
                'email' => $data->email,
                'contact_number' => $data->contact_number,
                'is_active' => $data->is_active,
            ]));

            // Update user details
            if ($employee->user_details) {
                $employee->user_details->update(array_filter([
                    'designation_id' => $data->designation_id,
                    'department_id' => $data->department_id,
                    'branch_id' => $data->branch_id,
                    'salary' => $data->basic_salary,
                ]));
            }

            DB::commit();

            // Clear permissions cache
            $this->permissionService->clearUserPermissionsCache($employee->user_id);

            return $employee->fresh(['user_details.designation', 'user_details.department']);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('EmployeeManagementService::updateEmployee failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'data' => $data->toArray(),
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء تحديث الموظف'
            ]);

            return null;
        }
    }

    /**
     * Deactivate employee with permission check
     * 
     * @param User $user Current user deactivating the employee
     * @param int $employeeId Target employee ID
     * @return bool
     */
    public function deactivateEmployee(User $user, int $employeeId): bool
    {
        try {
            $employee = User::find($employeeId);

            if (!$employee) {
                Log::error('EmployeeManagementService::deactivateEmployee failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'لا يوجد موظف بهذا الرقم'
                ]);
                return false;
            }

            $employee->update(['is_active' => 0]);

            // Clear permissions cache
            $this->permissionService->clearUserPermissionsCache($employee->user_id);

            return true;
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::deactivateEmployee failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء إلغاء نشاط الموظف'
            ]);

            return false;
        }
    }

    /**
     * Search employees with comprehensive text search
     * 
     * @param User $user Current user requesting the search
     * @param string $query Search query
     * @param array $options Additional search options
     * @return array
     */
    public function searchEmployees(User $user, string $query, array $options = []): array
    {
        try {
            // Build base query with permissions
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $baseQuery = $this->buildEmployeesQuery($user, new EmployeeFilterDTO(company_id: $companyId));

            // Apply comprehensive search
            $this->applyComprehensiveSearch($baseQuery, $query);

            // Apply additional filters from options
            if (!empty($options['department_id'])) {
                $baseQuery->whereHas('user_details', function ($q) use ($options) {
                    $q->where('department_id', $options['department_id']);
                });
            }

            if (!empty($options['designation_id'])) {
                $baseQuery->whereHas('user_details', function ($q) use ($options) {
                    $q->where('designation_id', $options['designation_id']);
                });
            }

            // Limit results for performance
            $limit = $options['limit'] ?? 50;
            $results = $baseQuery->limit($limit)->get();

            return [
                'employees' => $results,
                'total' => $results->count(),
                'query' => $query,
                'options' => $options
            ];
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::searchEmployees failed', [
                'user_id' => $user->user_id,
                'query' => $query,
                'options' => $options,
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء البحث عن الموظفين'
            ]);

            return [
                'employees' => [],
                'total' => 0,
                'query' => $query,
                'options' => $options
            ];
        }
    }

    /**
     * Get employees with advanced filtering
     * 
     * @param User $user Current user requesting the data
     * @param array $filters Advanced filter parameters
     * @return array
     */
    public function getEmployeesWithAdvancedFilters(User $user, array $filters): array
    {
        try {
            // Convert array to DTO
            $filterDTO = EmployeeFilterDTO::fromArray($filters);

            // Get base query
            $query = $this->buildEmployeesQuery($user, $filterDTO);

            // Apply all filters
            $this->applyFilters($query, $filterDTO);
            $this->applyAdvancedFilters($query, $filters);

            // Apply search if provided
            if ($filterDTO->search) {
                $this->applyComprehensiveSearch($query, $filterDTO->search);
            }

            // Apply sorting
            $this->applySorting($query, $filterDTO->sort_by, $filterDTO->sort_direction);

            // Get results with pagination
            $results = $query->paginate($filterDTO->limit, ['*'], 'page', $filterDTO->page);

            return [
                'employees' => $results->items(),
                'pagination' => [
                    'current_page' => $results->currentPage(),
                    'last_page' => $results->lastPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'from' => $results->firstItem(),
                    'to' => $results->lastItem(),
                ],
                'filters_applied' => $filters
            ];
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getEmployeesWithAdvancedFilters failed', [
                'user_id' => $user->user_id,
                'filters' => $filters,
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء جلب قائمة الموظفين'
            ]);

            return [
                'employees' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $filterDTO->limit ?? 20,
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                ],
                'filters_applied' => $filters
            ];
        }
    }
    /** 
     * @param User $user Current user requesting statistics
     * @return array
     */
    public function getEmployeeStatistics(User $user): array
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            // Get accessible user IDs for filtering stats (if not admin)
            $userIds = null;
            if (!$this->permissionService->isCompanyOwner($user)) {
                $subordinatesQuery = User::query();
                $subordinatesQuery = $this->permissionService->filterByCompany($subordinatesQuery, $user);
                $subordinatesQuery = $this->permissionService->filterSubordinates($subordinatesQuery, $user);
                $userIds = $subordinatesQuery->pluck('user_id')->toArray();
            }

            // Get advanced stats from Repository
            $advStats = $this->employeeRepository->getAdvancedStats($companyId, ['user_ids' => $userIds]);

            // Map and format results
            $stats = [
                'total_employees' => array_sum(array_column($advStats['employees_by_department'], 'total_employees')),
                'active_employees' => array_sum(array_column($advStats['employees_by_department'], 'active_employees')),
                'inactive_employees' => 0,
                'departments_count' => count($advStats['employees_by_department']),
                'designations_count' => count($advStats['employees_by_designation']),
                'average_salary' => round($advStats['salary_sums']->average_salary ?? 0, 2),
                'total_salary_cost' => round($advStats['salary_sums']->total_salary_cost ?? 0, 2),
                'employees_by_department' => array_map(function ($dept) {
                    return [
                        'department_id' => $dept->department_id,
                        'department_name' => $dept->department_name,
                        'total_employees' => (int)$dept->total_employees,
                        'active_employees' => (int)$dept->active_employees,
                        'inactive_employees' => (int)($dept->total_employees - $dept->active_employees),
                    ];
                }, $advStats['employees_by_department']),
                'employees_by_designation' => array_map(function ($desig) {
                    return [
                        'designation_id' => $desig->designation_id,
                        'designation_name' => $desig->designation_name,
                        'hierarchy_level' => $desig->hierarchy_level,
                        'total_employees' => (int)$desig->total_employees,
                        'active_employees' => (int)$desig->active_employees,
                        'inactive_employees' => (int)($desig->total_employees - $desig->active_employees),
                    ];
                }, $advStats['employees_by_designation']),
                'by_gender' => array_map(function ($g) {
                    $genderName = ($g->gender === 'M' || $g->gender === 'male' || $g->gender === '1') ? 'ذكر' : 'أنثى';
                    return ['gender' => $g->gender, 'gender_name' => $genderName, 'count' => (int)$g->count];
                }, $advStats['by_gender']),
                'by_age_group' => array_map(function ($age) {
                    return ['age_group' => $age->age_group, 'count' => (int)$age->count];
                }, $advStats['by_age_group']),
                'salary_statistics' => [
                    'min_salary' => round($advStats['salary_sums']->min_salary ?? 0, 2),
                    'max_salary' => round($advStats['salary_sums']->max_salary ?? 0, 2),
                    'employees_with_salary' => (int)($advStats['salary_sums']->employees_with_salary ?? 0),
                ],
                'recent_hires' => (int)$advStats['recent_hires_count'],
            ];

            $stats['inactive_employees'] = $stats['total_employees'] - $stats['active_employees'];

            return $stats;

            return $stats;
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getEmployeeStatistics failed', [
                'user_id' => $user->user_id,
                'error' => $e->getMessage()
            ]);

            return [
                'total_employees' => 0,
                'active_employees' => 0,
                'inactive_employees' => 0,
                'departments_count' => 0,
                'designations_count' => 0,
                'average_salary' => 0,
                'total_salary_cost' => 0,
                'by_department' => [],
                'by_designation' => [],
                // 'by_hierarchy_level' => [],
                'by_gender' => [],
                'by_age_group' => [],
                'salary_statistics' => [],
                'recent_hires' => 0,
            ];
        }
    }

    /**
     * Build base employees query with company and hierarchy filtering
     * 
     * @param User $user
     * @param EmployeeFilterDTO $filters
     * @return Builder
     */
    private function buildEmployeesQuery(User $user, EmployeeFilterDTO $filters): Builder
    {
        $query = User::with([
            'user_details.designation',
            'user_details.department',
            'user_details.branch'
        ]);

        // Apply company filtering
        $query = $this->permissionService->filterByCompany($query, $user);

        // Apply hierarchy filtering if not company owner
        if (!$this->permissionService->isCompanyOwner($user)) {
            $query = $this->permissionService->filterSubordinates($query, $user);
        }

        // Only staff users
        $query->where('user_type', 'staff');

        return $query;
    }

    /**
     * Apply filters to the query
     * 
     * @param Builder $query
     * @param EmployeeFilterDTO $filters
     */
    private function applyFilters(Builder $query, EmployeeFilterDTO $filters): void
    {
        if ($filters->department_id) {
            $query->whereHas('user_details', function ($q) use ($filters) {
                $q->where('department_id', $filters->department_id);
            });
        }

        if ($filters->designation_id) {
            $query->whereHas('user_details', function ($q) use ($filters) {
                $q->where('designation_id', $filters->designation_id);
            });
        }

        if (isset($filters->branch_id)) {
            $query->whereHas('user_details', function ($q) use ($filters) {
                $q->where('branch_id', $filters->branch_id);
            });
        }

        if (isset($filters->hierarchy_level)) {
            $query->whereHas('user_details.designation', function ($q) use ($filters) {
                $q->where('hierarchy_level', $filters->hierarchy_level);
            });
        }

        if ($filters->is_active !== null) {
            $query->where('is_active', $filters->is_active);
        }

        if ($filters->from_date) {
            $query->whereHas('user_details', function ($q) use ($filters) {
                $q->whereDate('date_of_joining', '>=', $filters->from_date);
            });
        }

        if ($filters->to_date) {
            $query->whereHas('user_details', function ($q) use ($filters) {
                $q->whereDate('date_of_joining', '<=', $filters->to_date);
            });
        }
    }

    /**
     * Apply comprehensive search to the query
     * 
     * @param Builder $query
     * @param string $search
     */
    private function applyComprehensiveSearch(Builder $query, string $search): void
    {
        $searchTerms = explode(' ', trim($search));

        $query->where(function ($q) use ($searchTerms) {
            foreach ($searchTerms as $term) {
                if (empty($term)) continue;

                $q->where(function ($subQ) use ($term) {
                    // Search in user basic info
                    $subQ->where('first_name', 'LIKE', "%{$term}%")
                        ->orWhere('last_name', 'LIKE', "%{$term}%")
                        ->orWhere('email', 'LIKE', "%{$term}%")
                        ->orWhere('contact_number', 'LIKE', "%{$term}%")
                        ->orWhere('username', 'LIKE', "%{$term}%")

                        // Search in user details
                        ->orWhereHas('user_details', function ($detailsQ) use ($term) {
                            $detailsQ->where('employee_id', 'LIKE', "%{$term}%");
                        })

                        // Search in department
                        ->orWhereHas('user_details.department', function ($deptQ) use ($term) {
                            $deptQ->where('department_name', 'LIKE', "%{$term}%");
                        })

                        // Search in designation
                        ->orWhereHas('user_details.designation', function ($desigQ) use ($term) {
                            $desigQ->where('designation_name', 'LIKE', "%{$term}%");
                        })

                        // Search in branch
                        ->orWhereHas('user_details.branch', function ($branchQ) use ($term) {
                            $branchQ->where('branch_name', 'LIKE', "%{$term}%");
                        });
                });
            }
        });
    }

    /**
     * Apply advanced filters to the query
     * 
     * @param Builder $query
     * @param array $filters
     */
    private function applyAdvancedFilters(Builder $query, array $filters): void
    {
        // Salary range filter
        if (!empty($filters['min_salary'])) {
            $query->whereHas('user_details', function ($q) use ($filters) {
                $q->where('salary', '>=', $filters['min_salary']);
            });
        }

        if (!empty($filters['max_salary'])) {
            $query->whereHas('user_details', function ($q) use ($filters) {
                $q->where('salary', '<=', $filters['max_salary']);
            });
        }

        // Age range filter (based on date_of_birth if available)
        if (!empty($filters['min_age'])) {
            $maxBirthDate = now()->subYears($filters['min_age'])->format('Y-m-d');
            $query->where('date_of_birth', '<=', $maxBirthDate);
        }

        if (!empty($filters['max_age'])) {
            $minBirthDate = now()->subYears($filters['max_age'])->format('Y-m-d');
            $query->where('date_of_birth', '>=', $minBirthDate);
        }

        // Gender filter
        if (!empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        // Multiple departments filter
        if (!empty($filters['department_ids']) && is_array($filters['department_ids'])) {
            $query->whereHas('user_details', function ($q) use ($filters) {
                $q->whereIn('department_id', $filters['department_ids']);
            });
        }

        // Multiple designations filter
        if (!empty($filters['designation_ids']) && is_array($filters['designation_ids'])) {
            $query->whereHas('user_details', function ($q) use ($filters) {
                $q->whereIn('designation_id', $filters['designation_ids']);
            });
        }

        // Multiple branches filter
        if (!empty($filters['branch_ids']) && is_array($filters['branch_ids'])) {
            $query->whereHas('user_details', function ($q) use ($filters) {
                $q->whereIn('branch_id', $filters['branch_ids']);
            });
        }

        // Hierarchy levels filter
        if (!empty($filters['hierarchy_levels']) && is_array($filters['hierarchy_levels'])) {
            $query->whereHas('user_details.designation', function ($q) use ($filters) {
                $q->whereIn('hierarchy_level', $filters['hierarchy_levels']);
            });
        }

        // Experience range filter (if experience field exists)
        if (!empty($filters['min_experience_years'])) {
            $maxHireDate = now()->subYears($filters['min_experience_years'])->format('Y-m-d');
            $query->whereHas('user_details', function ($q) use ($maxHireDate) {
                $q->where('hire_date', '<=', $maxHireDate);
            });
        }

        // Custom date range for hire date
        if (!empty($filters['hired_after'])) {
            $query->whereHas('user_details', function ($q) use ($filters) {
                $q->where('hire_date', '>=', $filters['hired_after']);
            });
        }

        if (!empty($filters['hired_before'])) {
            $query->whereHas('user_details', function ($q) use ($filters) {
                $q->where('hire_date', '<=', $filters['hired_before']);
            });
        }

        // Employment status filters
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        // User type filter (if needed)
        if (!empty($filters['user_type'])) {
            $query->where('user_type', $filters['user_type']);
        }
    }

    /**
     * Apply search conditions to query
     * 
     * @param Builder $query
     * @param string $search
     */
    private function applySearch(Builder $query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('first_name', 'LIKE', "%{$search}%")
                ->orWhere('last_name', 'LIKE', "%{$search}%")
                ->orWhere('email', 'LIKE', "%{$search}%")
                ->orWhere('contact_number', 'LIKE', "%{$search}%")
                ->orWhereHas('user_details', function ($subQ) use ($search) {
                    $subQ->where('employee_id', 'LIKE', "%{$search}%");
                });
        });
    }

    /**
     * Apply sorting to the query with advanced options
     * 
     * @param Builder $query
     * @param string|null $sortBy
     * @param string $sortDirection
     */
    private function applySorting(Builder $query, ?string $sortBy, string $sortDirection): void
    {
        $sortBy = $sortBy ?: 'first_name';
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? strtolower($sortDirection) : 'asc';

        switch ($sortBy) {
            case 'name':
            case 'full_name':
                $query->orderBy('first_name', $sortDirection)
                    ->orderBy('last_name', $sortDirection);
                break;

            case 'first_name':
                $query->orderBy('first_name', $sortDirection);
                break;

            case 'last_name':
                $query->orderBy('last_name', $sortDirection);
                break;

            case 'email':
                $query->orderBy('email', $sortDirection);
                break;

            case 'phone':
                $query->orderBy('phone', $sortDirection);
                break;

            case 'hire_date':
            case 'joining_date':
                $query->leftJoin('ci_erp_users_details as sort_details', 'ci_erp_users.user_id', '=', 'sort_details.user_id')
                    ->orderBy('sort_details.hire_date', $sortDirection)
                    ->select('ci_erp_users.*'); // Ensure we only select user columns
                break;

            case 'salary':
                $query->leftJoin('ci_erp_users_details as sort_details2', 'ci_erp_users.user_id', '=', 'sort_details2.user_id')
                    ->orderBy('sort_details2.salary', $sortDirection)
                    ->select('ci_erp_users.*');
                break;

            case 'department':
            case 'department_name':
                $query->leftJoin('ci_erp_users_details as sort_details3', 'ci_erp_users.user_id', '=', 'sort_details3.user_id')
                    ->leftJoin('ci_departments as sort_dept', 'sort_details3.department_id', '=', 'sort_dept.department_id')
                    ->orderBy('sort_dept.department_name', $sortDirection)
                    ->select('ci_erp_users.*');
                break;

            case 'designation':
            case 'designation_name':
                $query->leftJoin('ci_erp_users_details as sort_details4', 'ci_erp_users.user_id', '=', 'sort_details4.user_id')
                    ->leftJoin('ci_designations as sort_desig', 'sort_details4.designation_id', '=', 'sort_desig.designation_id')
                    ->orderBy('sort_desig.designation_name', $sortDirection)
                    ->select('ci_erp_users.*');
                break;

            case 'hierarchy_level':
                $query->leftJoin('ci_erp_users_details as sort_details5', 'ci_erp_users.user_id', '=', 'sort_details5.user_id')
                    ->leftJoin('ci_designations as sort_desig2', 'sort_details5.designation_id', '=', 'sort_desig2.designation_id')
                    ->orderBy('sort_desig2.hierarchy_level', $sortDirection)
                    ->select('ci_erp_users.*');
                break;

            case 'branch':
            case 'branch_name':
                $query->leftJoin('ci_erp_users_details as sort_details6', 'ci_erp_users.user_id', '=', 'sort_details6.user_id')
                    ->leftJoin('ci_branchs as sort_branch', 'sort_details6.branch_id', '=', 'sort_branch.branch_id')
                    ->orderBy('sort_branch.branch_name', $sortDirection)
                    ->select('ci_erp_users.*');
                break;

            case 'is_active':
            case 'status':
                $query->orderBy('is_active', $sortDirection);
                break;

            case 'created_at':
                $query->orderBy('created_at', $sortDirection);
                break;

            case 'updated_at':
                $query->orderBy('updated_at', $sortDirection);
                break;

            default:
                // Default sorting by first name
                $query->orderBy('first_name', $sortDirection)
                    ->orderBy('last_name', $sortDirection);
        }
    }

    /**
     * Get employees by department with advanced options
     * 
     * @param User $user Current user
     * @param int $departmentId Department ID
     * @param array $options Additional options
     * @return array
     */
    public function getEmployeesByDepartment(User $user, int $departmentId, array $options = []): array
    {
        $filters = array_merge($options, ['department_id' => $departmentId]);

        try {
            return $this->getEmployeesWithAdvancedFilters($user, $filters);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getEmployeesByDepartment failed', [
                'user_id' => $user->user_id,
                'department_id' => $departmentId,
                'options' => $options,
                'error' => $e->getMessage()
            ]);

            return [
                'employees' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $options['limit'] ?? 20,
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                ],
                'filters_applied' => $filters
            ];
        }
    }

    /**
     * Get employees by designation with advanced options
     * 
     * @param User $user Current user
     * @param int $designationId Designation ID
     * @param array $options Additional options
     * @return array
     */
    public function getEmployeesByDesignation(User $user, int $designationId, array $options = []): array
    {
        $filters = array_merge($options, ['designation_id' => $designationId]);

        try {
            return $this->getEmployeesWithAdvancedFilters($user, $filters);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getEmployeesByDesignation failed', [
                'user_id' => $user->user_id,
                'designation_id' => $designationId,
                'options' => $options,
                'error' => $e->getMessage()
            ]);

            return [
                'employees' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $options['limit'] ?? 20,
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                ],
                'filters_applied' => $filters
            ];
        }
    }

    /**
     * Get search and filter statistics
     * 
     * @param User $user Current user
     * @param array $filters Applied filters
     * @return array
     */
    public function getFilterStatistics(User $user, array $filters = []): array
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $baseQuery = $this->buildEmployeesQuery($user, new EmployeeFilterDTO(company_id: $companyId));

            // Clone query for different statistics
            $totalQuery = clone $baseQuery;
            $filteredQuery = clone $baseQuery;

            // Apply filters to get filtered count
            if (!empty($filters)) {
                $filterDTO = EmployeeFilterDTO::fromArray($filters);
                $this->applyFilters($filteredQuery, $filterDTO);
                $this->applyAdvancedFilters($filteredQuery, $filters);

                if ($filterDTO->search) {
                    $this->applyComprehensiveSearch($filteredQuery, $filterDTO->search);
                }
            }

            $stats = [
                'total_accessible' => $totalQuery->count(),
                'filtered_count' => $filteredQuery->count(),
                'filters_applied' => $filters,
                'breakdown' => []
            ];

            // Get breakdown by department if no department filter applied
            if (empty($filters['department_id']) && empty($filters['department_ids'])) {
                $deptBreakdown = (clone $filteredQuery)
                    ->join('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
                    ->join('ci_departments', 'ci_erp_users_details.department_id', '=', 'ci_departments.department_id')
                    ->select('ci_departments.department_name', DB::raw('COUNT(*) as count'))
                    ->groupBy('ci_departments.department_id', 'ci_departments.department_name')
                    ->get();

                $stats['breakdown']['by_department'] = $deptBreakdown->pluck('count', 'department_name')->toArray();
            }

            // Get breakdown by designation if no designation filter applied
            if (empty($filters['designation_id']) && empty($filters['designation_ids'])) {
                $desigBreakdown = (clone $filteredQuery)
                    ->join('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
                    ->join('ci_designations', 'ci_erp_users_details.designation_id', '=', 'ci_designations.designation_id')
                    ->select('ci_designations.designation_name', DB::raw('COUNT(*) as count'))
                    ->groupBy('ci_designations.designation_id', 'ci_designations.designation_name')
                    ->get();

                $stats['breakdown']['by_designation'] = $desigBreakdown->pluck('count', 'designation_name')->toArray();
            }

            // Get breakdown by status
            $statusBreakdown = (clone $filteredQuery)
                ->select('is_active', DB::raw('COUNT(*) as count'))
                ->groupBy('is_active')
                ->get();

            $stats['breakdown']['by_status'] = [
                'active' => $statusBreakdown->where('is_active', 1)->first()->count ?? 0,
                'inactive' => $statusBreakdown->where('is_active', 0)->first()->count ?? 0,
            ];

            return $stats;
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getFilterStatistics failed', [
                'user_id' => $user->user_id,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'total_accessible' => 0,
                'filtered_count' => 0,
                'filters_applied' => $filters,
                'breakdown' => []
            ];
        }
    }

    // /**
    //  * Check if editor can edit target employee
    //  * 
    //  * @param User $editor
    //  * @param User $target
    //  * @return bool
    //  */
    // private function canEditEmployee(User $editor, User $target): bool
    // {
    //     // Check basic permission
    //     if (!$this->permissionService->checkPermission($editor, 'employee.edit')) {
    //         throw new \Exception(message: 'فشل في تعديل بيانات الموظف');
    //     }

    //     // Use existing hierarchy logic from SimplePermissionService
    //     return $this->permissionService->canApproveEmployeeRequests($editor, $target);
    // }

    // /**
    //  * Check if user can delete employee (uses SimplePermissionService logic)
    //  * 
    //  * @param User $deleter
    //  * @param User $target
    //  * @return bool
    //  */
    // private function canDeleteEmployee(User $deleter, User $target): bool
    // {
    //     // Check basic permission
    //     if (!$this->permissionService->checkPermission($deleter, 'employee.delete')) {
    //         throw new \Exception(message: 'فشل في حذف بيانات الموظف');
    //     }

    //     // Use existing hierarchy logic from SimplePermissionService
    //     return $this->permissionService->canApproveEmployeeRequests($deleter, $target);
    // }

    // /**
    //  * Get employee documents with permission check
    //  * 
    //  * @param User $user Current user requesting the data
    //  * @param int $employeeId Target employee ID
    //  * @return array|null
    //  */
    // public function getEmployeeDocuments(User $user, int $employeeId): ?array
    // {
    //     try {
    //         $employee = User::find($employeeId);

    //         if (!$employee) {
    //             Log::error('EmployeeManagementService::getEmployeeDocuments failed', [
    //                 'user_id' => $user->user_id,
    //                 'employee_id' => $employeeId,
    //                 'error' => 'Employee not found',
    //             ]);
    //             throw new \Exception(message: 'فشل في الحصول على الوثائق الموظف');
    //         }

    //         // Check if user can access this employee
    //         if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
    //             Log::error('EmployeeManagementService::getEmployeeDocuments failed', [
    //                 'user_id' => $user->user_id,
    //                 'employee_id' => $employeeId,
    //                 'error' => 'User does not have permission to view employee documents',
    //             ]);
    //             throw new \Exception(message: 'فشل في الحصول على الوثائق الموظف');
    //         }

    //         $companyId = $this->permissionService->getEffectiveCompanyId($user);

    //         // Get real documents from database
    //         $documentsQuery = DB::table('ci_users_documents')
    //             ->where('company_id', $companyId)
    //             ->where('user_id', $employeeId)
    //             ->select([
    //                 'document_id as id',
    //                 'document_type',
    //                 'document_name as file_name',
    //                 'document_file as file_path',
    //                 'expiry_date',
    //                 'created_at'
    //             ])
    //             ->orderBy('created_at', 'desc');

    //         $realDocuments = $documentsQuery->get();

    //         $documents = [];

    //         if ($realDocuments->count() > 0) {
    //             // Use real documents from database
    //             foreach ($realDocuments as $doc) {
    //                 $documents[] = [
    //                     'id' => $doc->id,
    //                     'document_type' => $doc->document_type,
    //                     'file_name' => $doc->file_name,
    //                     'file_path' => $doc->file_path ? '/storage/documents/' . $doc->file_path : null,
    //                     'file_size' => 'غير محدد', // File size not stored in database
    //                     'expiry_date' => $doc->expiry_date,
    //                     'uploaded_at' => $doc->created_at,
    //                     'uploaded_by' => 'النظام', // Uploader not stored in database
    //                 ];
    //             }
    //         } else {
    //             // Return empty array if no documents found
    //             $documents = [];
    //         }

    //         return [
    //             'employee' => [
    //                 'id' => $employee->user_id,
    //                 'name' => $employee->first_name . ' ' . $employee->last_name,
    //                 'employee_id' => $employee->user_details->employee_id ?? 'EMP' . str_pad($employee->user_id, 4, '0', STR_PAD_LEFT)
    //             ],
    //             'documents' => $documents,
    //             'total' => count($documents),
    //             'summary' => [
    //                 'total_size' => count($documents) > 0 ? 'غير محدد' : '0 KB',
    //                 'document_types' => array_unique(array_column($documents, 'document_type'))
    //             ]
    //         ];
    //     } catch (\Exception $e) {
    //         Log::error('EmployeeManagementService::getEmployeeDocuments failed', [
    //             'user_id' => $user->user_id,
    //             'employee_id' => $employeeId,
    //             'error' => $e->getMessage()
    //         ]);

    //         throw new \Exception(message: 'فشل في الحصول على اجازات الموظف');
    //     }
    // }

    /**
     * Get employee leave balance with permission check
     * 
     * @param User $user Current user requesting the data
     * @param int $employeeId Target employee ID
     * @return array|null
     */
    public function getEmployeeLeaveBalance(User $user, int $employeeId): ?array
    {
        try {
            $employee = User::find($employeeId);

            if (!$employee) {
                Log::error('EmployeeManagementService::getEmployeeLeaveBalance failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'error' => 'Employee not found',
                ]);
                throw new \Exception(message: 'فشل في الحصول على اجازات الموظف');
            }

            // Check if user can access this employee
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::getEmployeeLeaveBalance failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'error' => 'User does not have permission to view employee leave balance',
                ]);
                throw new \Exception(message: 'فشل في الحصول على اجازات الموظف');
            }

            $currentYear = now()->year;
            $companyId = $employee->company_id;

            // Get Office Shift hours_per_day (from Repository through Model if needed, or stick to current logic but cleaner)
            $shiftId = $employee->user_details->office_shift_id ?? null;
            $hoursPerDay = 8;
            if ($shiftId) {
                $shift = $this->employeeRepository->getUserWithHierarchyInfo($employeeId)['user_details']['office_shift'] ?? null;
                // Wait, getUserWithHierarchyInfo returns array. Better way.
                $shift = DB::table('ci_office_shifts')->where('office_shift_id', $shiftId)->first();
                if ($shift && !empty($shift->hours_per_day)) {
                    $hoursPerDay = (int)$shift->hours_per_day;
                }
            }

            // Get data from Repository
            $leaveTypes = $this->employeeRepository->getLeaveTypes($companyId);
            $approvedLeaves = $this->employeeRepository->getLeaveApplicationsByYear($employeeId, $companyId, $currentYear, 1);
            $pendingLeaves = $this->employeeRepository->getLeaveApplicationsByYear($employeeId, $companyId, $currentYear, 0);
            $approvedAdjustments = $this->employeeRepository->getLeaveAdjustmentsByYear($employeeId, $companyId, $currentYear);

            // Helper function to parse quota from field_one
            $parseQuota = function ($fieldOne, $companyId = 0) {
                if (empty($fieldOne) || $fieldOne === 'Null') {
                    return 0;
                }

                // Try to unserialize the PHP array
                $data = @unserialize($fieldOne);
                if ($data && isset($data['quota_assign']) && is_array($data['quota_assign'])) {
                    // Return quota for company index (default to index 0)
                    return (int)($data['quota_assign'][$companyId] ?? $data['quota_assign'][0] ?? 0);
                }

                // If it's a simple number
                if (is_numeric($fieldOne)) {
                    return (int)$fieldOne;
                }

                return 0;
            };

            // Calculate usage by leave type
            $usedHours = [];
            $pendingHours = [];

            // Calculate used hours from approved leaves
            foreach ($approvedLeaves as $leave) {
                if (!isset($usedHours[$leave->leave_type_id])) {
                    $usedHours[$leave->leave_type_id] = 0;
                }
                $usedHours[$leave->leave_type_id] += (int)$leave->leave_hours;
            }

            // Calculate pending hours
            foreach ($pendingLeaves as $leave) {
                if (!isset($pendingHours[$leave->leave_type_id])) {
                    $pendingHours[$leave->leave_type_id] = 0;
                }
                $pendingHours[$leave->leave_type_id] += (int)$leave->leave_hours;
            }

            // Add approved adjustments (these add to available balance)
            foreach ($approvedAdjustments as $adjustment) {
                if (!isset($usedHours[$adjustment->leave_type_id])) {
                    $usedHours[$adjustment->leave_type_id] = 0;
                }
                // Adjustments are positive additions to balance, so subtract from used
                $usedHours[$adjustment->leave_type_id] -= (int)$adjustment->adjust_hours;
                $usedHours[$adjustment->leave_type_id] = max(0, $usedHours[$adjustment->leave_type_id]); // Don't go negative
            }

            // Build leave types response based on actual database leave types
            $leaveTypesResponse = [];

            // Process actual leave types from database
            foreach ($leaveTypes as $leaveType) {
                $categoryName = trim($leaveType->category_name);
                $totalHours = $parseQuota($leaveType->field_one, 0); // Use company index 0 as default

                // Skip if no quota assigned
                if ($totalHours <= 0) {
                    continue;
                }

                $used = $usedHours[$leaveType->constants_id] ?? 0;
                $pending = $pendingHours[$leaveType->constants_id] ?? 0;
                $remaining = max(0, $totalHours - $used - $pending);

                // Use actual category name as key (make it safe for JSON)
                $safeKey = $leaveType->constants_id; // Use the ID as key for consistency

                $leaveTypesResponse[$safeKey] = [
                    'name' => $categoryName,
                    'total_days' => round($totalHours / $hoursPerDay, 2), // Convert hours to days using shift specific hours
                    'used_days' => round($used / $hoursPerDay, 2),
                    'pending_days' => round($pending / $hoursPerDay, 2),
                    'remaining_days' => round($remaining / $hoursPerDay, 2),
                    'total_hours' => (int)$totalHours,
                    'used_hours' => (int)$used,
                    'pending_hours' => (int)$pending,
                    'remaining_hours' => (int)$remaining
                ];
            }

            // Calculate totals (Days)
            $totalAllocated = array_sum(array_column($leaveTypesResponse, 'total_days'));
            $totalUsed = array_sum(array_column($leaveTypesResponse, 'used_days'));
            $totalPending = array_sum(array_column($leaveTypesResponse, 'pending_days'));
            $totalRemaining = array_sum(array_column($leaveTypesResponse, 'remaining_days'));

            // Calculate totals (Hours)
            $totalAllocatedHours = array_sum(array_column($leaveTypesResponse, 'total_hours'));
            $totalUsedHours = array_sum(array_column($leaveTypesResponse, 'used_hours'));
            $totalPendingHours = array_sum(array_column($leaveTypesResponse, 'pending_hours'));
            $totalRemainingHours = array_sum(array_column($leaveTypesResponse, 'remaining_hours'));

            // Get recent leaves (last 5 approved leaves) from Repository
            $recentLeaves = $this->employeeRepository->getRecentLeaves($employeeId, $companyId, 5);

            $recentLeavesFormatted = [];
            foreach ($recentLeaves as $leave) {
                $categoryName = $leave->category_name ?? 'غير محدد';

                // Use actual category name as type
                $safeKey = $leave->leave_type_id; // Use the leave type ID

                // Calculate days from hours using shift specific hours
                $days = round((int)$leave->leave_hours / $hoursPerDay, 2);

                $recentLeavesFormatted[] = [
                    'type' => $safeKey,
                    'type_name' => $categoryName,
                    'start_date' => $leave->from_date,
                    'end_date' => $leave->to_date,
                    'days' => $days,
                    'hours' => (int)$leave->leave_hours,
                    'status' => 'approved',
                    'reason' => $leave->reason ?: 'غير محدد'
                ];
            }

            return [
                'employee' => [
                    'id' => $employee->user_id,
                    'name' => trim($employee->first_name . ' ' . $employee->last_name),
                    'employee_id' => $employee->user_details->employee_id ?? 'EMP' . str_pad($employee->user_id, 4, '0', STR_PAD_LEFT),
                    'hours_per_day' => $hoursPerDay
                ],
                'year' => $currentYear,
                'leave_types' => $leaveTypesResponse,
                'summary' => [
                    'total_allocated_days' => round($totalAllocated, 2),
                    'total_used_days' => round($totalUsed, 2),
                    'total_pending_days' => round($totalPending, 2),
                    'total_remaining_days' => round($totalRemaining, 2),
                    'total_allocated_hours' => (int)$totalAllocatedHours,
                    'total_used_hours' => (int)$totalUsedHours,
                    'total_pending_hours' => (int)$totalPendingHours,
                    'total_remaining_hours' => (int)$totalRemainingHours,
                    'utilization_rate' => $totalAllocated > 0 ? round(($totalUsed / $totalAllocated) * 100, 2) : 0
                ],
                'recent_leaves' => $recentLeavesFormatted
            ];
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getEmployeeLeaveBalance failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \Exception(message: 'فشل في الحصول على اجازات الموظف');
        }
    }

    /**
     * Get employee attendance records with permission check
     * 
     * @param User $user Current user requesting the data
     * @param int $employeeId Target employee ID
     * @param array $options Additional options (limit, from_date, to_date)
     * @return array|null
     */
    public function getEmployeeAttendance(User $user, int $employeeId, array $options = []): ?array
    {
        try {
            $employee = User::with('user_details')->find($employeeId);

            if (!$employee) {
                Log::error('EmployeeManagementService::getEmployeeAttendance failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'error' => 'Employee not found',
                ]);
                throw new \Exception(message: 'فشل في الحصول على حضور الموظف');
            }

            // Check if user can access this employee
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::getEmployeeAttendance failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'error' => 'User does not have permission to view employee attendance',
                ]);
                throw new \Exception(message: 'فشل في الحصول على حضور الموظف');
            }

            $limit = (int) ($options['limit'] ?? 30);
            $fromDate = $options['from_date'] ?? now()->subDays($limit - 1)->format('Y-m-d');
            $toDate = $options['to_date'] ?? now()->format('Y-m-d');

            $companyId = $employee->company_id;

            // 1. Fetch Attendance records (ci_timesheet) from Repository
            $attendanceRecords = $this->employeeRepository->getAttendanceRecords($employeeId, $fromDate, $toDate);

            // 2. Fetch Holidays (ci_holidays) from Repository
            $holidays = $this->employeeRepository->getHolidays($companyId, $fromDate, $toDate);

            // 3. Fetch Approved Leaves (ci_leave_applications) from Repository
            $leaves = $this->employeeRepository->getApprovedLeaves($employeeId, $fromDate, $toDate);

            // 4. Get Office Shift to determine weekends
            $shiftId = $employee->user_details->office_shift_id ?? null;
            $officeShift = $shiftId ? OfficeShift::find($shiftId) : null;

            $attendance = [];
            $presentDays = 0;
            $totalHours = 0;
            $lateDays = 0;
            $earlyLeaveDays = 0;

            $period = CarbonPeriod::create($fromDate, $toDate);

            foreach ($period as $date) {
                $dateStr = $date->format('Y-m-d');
                $dayNameAr = $date->locale('ar')->dayName;

                // Priority 1: Attendance Record
                if (isset($attendanceRecords[$dateStr])) {
                    $record = $attendanceRecords[$dateStr];

                    // Helper to parse duration string (HH:MM or HH:MM:SS) to hours float
                    $parseHours = function ($val) {
                        if (empty($val) || $val === '00:00' || $val === '00:00:00' || $val === '0' || $val === 0) return 0;
                        // If it contains a date pattern (corrupted data), don't parse as duration
                        if (preg_match('/\d{4}-\d{2}-\d{2}/', (string)$val)) return 0;

                        if (strpos((string)$val, ':') !== false) {
                            $parts = explode(':', (string)$val);
                            $h = (int)($parts[0] ?? 0);
                            $m = (int)($parts[1] ?? 0);
                            return $h + ($m / 60);
                        }
                        return (float)$val;
                    };

                    // Helper to format hours float back to HH:MM format
                    $formatHours = function ($hours) {
                        if ($hours <= 0) return '0:00';
                        $h = floor(round($hours * 60) / 60);
                        $m = round($hours * 60) % 60;
                        return sprintf('%d:%02d', $h, $m);
                    };

                    $worked = $parseHours($record->total_work);
                    $overtime = 0;
                    $isLate = false;
                    $isEarlyLeave = false;

                    // Effective Clock Out detection (handling corrupted database where overtime field contains exit timestamp)
                    $effectiveClockOut = $record->clock_out;
                    if (empty($effectiveClockOut) && !empty($record->overtime) && preg_match('/\d{4}-\d{2}-\d{2}/', (string)$record->overtime)) {
                        $effectiveClockOut = $record->overtime;
                    }

                    // If we have an office shift, recalculate late/early/overtime based on timestamps
                    // This fixes corrupted duration fields in the database
                    if ($officeShift) {
                        if ($record->clock_in) {
                            $lateStr = $officeShift->calculateTimeLate($dateStr, $record->clock_in);
                            $isLate = $lateStr !== '00:00';
                        }

                        if ($effectiveClockOut) {
                            $earlyStr = $officeShift->calculateEarlyLeaving($dateStr, $effectiveClockOut);
                            $isEarlyLeave = $earlyStr !== '00:00';

                            $overtimeStr = $officeShift->calculateOvertime($dateStr, $effectiveClockOut);
                            $overtime = $parseHours($overtimeStr);
                        }
                    } else {
                        // Fallback to database values if no shift info
                        $overtime = $parseHours($record->overtime);
                        $lateAmount = $parseHours($record->time_late);
                        $isLate = $lateAmount > 0;
                        $isEarlyLeave = $parseHours($record->early_leaving) > 0;
                    }

                    $attendance[] = [
                        'date' => $dateStr,
                        'day_name' => $dayNameAr,
                        'check_in' => $record->clock_in ? date('H:i:s', strtotime($record->clock_in)) : null,
                        'check_out' => $effectiveClockOut ? date('H:i:s', strtotime($effectiveClockOut)) : null,
                        'hours_worked' => $formatHours($worked),
                        'status' => 'present',
                        'is_late' => $isLate,
                        'is_early_leave' => $isEarlyLeave,
                        'overtime_hours' => $formatHours($overtime),
                        'notes' => $record->attendance_status === 'Holiday Work' ? 'عمل يوم عطلة' : ($isLate ? 'تأخير' : ($isEarlyLeave ? 'مغادرة مبكرة' : null))
                    ];

                    $presentDays++;
                    $totalHours += $worked;
                    if ($isLate) $lateDays++;
                    if ($isEarlyLeave) $earlyLeaveDays++;
                    continue;
                }

                // Priority 2: Approved Leave
                $leaveOnDay = $leaves->first(function ($l) use ($dateStr) {
                    $start = is_string($l->from_date) ? $l->from_date : $l->from_date->format('Y-m-d');
                    $end = is_string($l->to_date) ? $l->to_date : $l->to_date->format('Y-m-d');
                    return $dateStr >= $start && $dateStr <= $end;
                });

                if ($leaveOnDay) {
                    $attendance[] = [
                        'date' => $dateStr,
                        'day_name' => $dayNameAr,
                        'check_in' => null,
                        'check_out' => null,
                        'hours_worked' => '0:00',
                        'status' => 'leave',
                        'is_late' => false,
                        'is_early_leave' => false,
                        'overtime_hours' => '0:00',
                        'notes' => 'إجازة: ' . ($leaveOnDay->leaveType->category_name ?? 'إجازة معتمدة')
                    ];
                    continue;
                }

                // Priority 3: Holiday
                $holidayOnDay = $holidays->first(function ($h) use ($dateStr) {
                    $start = is_string($h->start_date) ? $h->start_date : $h->start_date->format('Y-m-d');
                    $end = is_string($h->end_date) ? $h->end_date : $h->end_date->format('Y-m-d');
                    return $dateStr >= $start && $dateStr <= $end;
                });

                if ($holidayOnDay) {
                    $attendance[] = [
                        'date' => $dateStr,
                        'day_name' => $dayNameAr,
                        'check_in' => null,
                        'check_out' => null,
                        'hours_worked' => '0:00',
                        'status' => 'holiday',
                        'is_late' => false,
                        'is_early_leave' => false,
                        'overtime_hours' => '0:00',
                        'notes' => 'عطلة: ' . ($holidayOnDay->event_name)
                    ];
                    continue;
                }

                // Priority 4: Weekly Off
                $isDayOff = $officeShift ? $officeShift->isDayOff($dateStr) : ($date->isFriday() || $date->isSaturday());
                if ($isDayOff) {
                    $attendance[] = [
                        'date' => $dateStr,
                        'day_name' => $dayNameAr,
                        'check_in' => null,
                        'check_out' => null,
                        'hours_worked' => '0:00',
                        'status' => 'weekend',
                        'is_late' => false,
                        'is_early_leave' => false,
                        'overtime_hours' => '0:00',
                        'notes' => 'عطلة إسبوعية'
                    ];
                    continue;
                }

                // Default: Absent
                if ($date->isPast() && !$date->isToday()) {
                    $attendance[] = [
                        'date' => $dateStr,
                        'day_name' => $dayNameAr,
                        'check_in' => null,
                        'check_out' => null,
                        'hours_worked' => '0:00',
                        'status' => 'absent',
                        'is_late' => false,
                        'is_early_leave' => false,
                        'overtime_hours' => '0:00',
                        'notes' => 'غياب'
                    ];
                }
            }

            $workingDaysCount = count($attendance);
            $absentDays = collect($attendance)->where('status', 'absent')->count();
            $averageHours = $presentDays > 0 ? $formatHours($totalHours / $presentDays) : '0:00';
            $attendanceRate = $workingDaysCount > 0 ? round(($presentDays / $workingDaysCount) * 100, 2) : 0;
            $punctualityRate = $presentDays > 0 ? round((($presentDays - $lateDays) / $presentDays) * 100, 2) : 0;

            return [
                'employee' => [
                    'id' => $employee->user_id,
                    'name' => $employee->first_name . ' ' . $employee->last_name,
                    'employee_id' => $employee->user_details->employee_id ?? '7'
                ],
                'period' => [
                    'from' => $fromDate,
                    'to' => $toDate,
                    'total_days' => $workingDaysCount
                ],
                'attendance' => array_values(array_reverse($attendance)),
                'summary' => [
                    'total_working_days' => $workingDaysCount,
                    'present_days' => $presentDays,
                    'absent_days' => $absentDays,
                    'late_days' => $lateDays,
                    'early_leave_days' => $earlyLeaveDays,
                    'total_hours_worked' => round($totalHours, 2),
                    'average_hours_per_day' => $averageHours,
                    'attendance_rate' => $attendanceRate,
                    'punctuality_rate' => $punctualityRate
                ]
            ];
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getEmployeeAttendance failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \Exception(message: 'فشل في الحصول على حضور الموظف', code: 500);
        }
    }

    /**
     * Change employee password
     */
    public function changeEmployeePassword(User $user, int $employeeId, string $password): bool
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // For self password change, get the user directly
            if ($user->user_id === $employeeId) {
                $employee = $user; // Use the authenticated user directly
            } else {
                // For changing other employees' passwords, use the repository method
                $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);
            }

            if (!$employee) {
                Log::warning('Employee not found for password change', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'effective_company_id' => $effectiveCompanyId,
                    'message' => 'فشل في تعديل كلمة المرور'
                ]);
                throw new \Exception(message: 'فشل في تعديل كلمة المرور', code: 404);
            }

            // Allow users to change their own password, or check permissions for others
            $canModify = ($user->user_id === $employeeId) ||
                $this->permissionService->canViewEmployeeRequests($user, $employee);

            if (!$canModify) {
                Log::warning('Permission denied for password change', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'is_self' => $user->user_id === $employeeId,
                    'message' => 'فشل في تعديل كلمة المرور'
                ]);
                throw new \Exception(message: 'فشل في تعديل كلمة المرور', code: 403);
            }

            // Hash password and update using repository
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // For self password change, use the user's actual company_id
            $targetCompanyId = ($user->user_id === $employeeId) ? $user->company_id : $effectiveCompanyId;

            return $this->employeeRepository->updateEmployeePassword($employeeId, $targetCompanyId, $hashedPassword);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::changeEmployeePassword failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'message' => 'فشل في تعديل كلمة المرور'
            ]);
            throw new \Exception(message: 'فشل في تعديل كلمة المرور', code: 500);
        }
    }

    /**
     * Upload employee profile image
     */
    public function uploadEmployeeProfileImage(User $user, int $employeeId, $imageFile): ?array
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            Log::info('EmployeeManagementService::uploadEmployeeProfileImage started', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'effective_company_id' => $effectiveCompanyId
            ]);

            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);

            if (!$employee) {
                Log::warning('Employee not found or not in same company', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'effective_company_id' => $effectiveCompanyId
                ]);
                throw new \Exception(message: 'فشل في تعديل الصورة الشخصية', code: 404);
            }

            Log::info('Employee found', [
                'employee_id' => $employee->user_id,
                'employee_company_id' => $employee->company_id ?? 'N/A'
            ]);

            // Check if user can modify this employee
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::warning('User does not have permission to modify this employee', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'user_type' => $user->user_type,
                    'employee_company_id' => $employee->company_id ?? 'N/A',
                    'message' => 'ليس لديك الصلاحية لتعديل صورة هذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك الصلاحية لتعديل صورة هذا الموظف', code: 403);
            }

            Log::info(message: 'Permission check passed, proceeding with file upload');

            // Upload image using FileUploadService
            $uploadResult = $this->fileUploadService->uploadProfileImage($imageFile, $employeeId);
            if (!$uploadResult) {
                Log::error('File upload failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'error' => 'File upload failed',
                    'message' => 'فشل في تعديل الصورة الشخصية'
                ]);
                throw new \Exception(message: 'فشل في تعديل الصورة الشخصية', code: 500);
            }

            // Update employee profile image using repository
            $success = $this->employeeRepository->updateEmployeeProfileImage($employeeId, $uploadResult['filename']);
            if (!$success) {
                Log::error('Database update failed, deleting uploaded file', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'error' => 'Database update failed',
                    'message' => 'فشل في تعديل صورة هذا الموظف'
                ]);
                // Delete uploaded file if database update failed
                $this->fileUploadService->deleteFile($uploadResult['file_path']);
                throw new \Exception(message: 'فشل في تعديل صورة هذا الموظف', code: 500);
            }

            Log::info('Profile image updated successfully');

            return [
                'profile_image_url' => $uploadResult['file_url']
            ];
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::uploadEmployeeProfileImage failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'message' => 'فشل في تعديل صورة هذا الموظف'
            ]);

            throw new \Exception(message: 'فشل في تعديل صورة هذا الموظف', code: 500);
        }
    }

    /**
     * Upload employee document
     */
    public function uploadEmployeeDocument(User $user, int $employeeId, array $documentData): ?array
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);

            if (!$employee) {
                Log::warning('Employee not found', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'error' => 'Employee not found',
                    'message' => 'فشل في إضافة الوثيقة'
                ]);
                throw new \Exception(message: 'فشل في إضافة الوثيقة', code: 404);
            }

            // Check if user can modify this employee
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::warning('Permission denied for document upload', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'error' => 'Permission denied',
                    'message' => 'ليس لديك الصلاحية لإضافة الوثيقة لهذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك الصلاحية لإضافة الوثيقة لهذا الموظف', code: 403);
            }

            // Upload document using FileUploadService
            $documentFile = $documentData['document_file'];
            $uploadResult = $this->fileUploadService->uploadDocument($documentFile, $employeeId, 'documents', $documentData['document_type']);
            if (!$uploadResult) {
                Log::warning('Failed to upload document', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'error' => 'Failed to upload document',
                    'message' => 'فشل في إضافة الوثيقة'
                ]);
                throw new \Exception(message: 'فشل في إضافة الوثيقة', code: 500);
            }

            // Insert document record using repository
            $documentId = $this->employeeRepository->insertEmployeeDocument([
                'user_id' => $employeeId,
                'company_id' => $effectiveCompanyId,
                'document_name' => $documentData['document_name'],
                'document_type' => $documentData['document_type'],
                'file_path' => $uploadResult['filename'],
                'expiration_date' => $documentData['expiration_date'] ?? null,
            ]);

            if (!$documentId) {
                // Delete uploaded file if database insert failed
                Log::warning('Failed to insert document record', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'error' => 'Failed to insert document record',
                    'message' => 'فشل في إضافة الوثيقة'
                ]);
                $this->fileUploadService->deleteFile($uploadResult['file_path']);
                throw new \Exception(message: 'فشل في إضافة الوثيقة', code: 500);
            }

            return [
                'document_id' => $documentId,
                'document_url' => $uploadResult['file_url']
            ];
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::uploadEmployeeDocument failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'message' => 'فشل في إضافة الوثيقة'
            ]);

            throw new \Exception(message: 'فشل في إضافة الوثيقة', code: 500);
        }
    }

    /**
     * Update employee profile info (username and email)
     */
    public function updateEmployeeProfileInfo(User $user, int $employeeId, array $profileData): bool
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);

            if (!$employee) {
                Log::warning('Employee not found', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'error' => 'Employee not found',
                    'message' => 'فشل في تعديل البيانات الشخصية'
                ]);
                throw new \Exception(message: 'فشل في تعديل البيانات الشخصية', code: 404);
            }

            // Check if user can modify this employee
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::warning('Permission denied for profile update', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'error' => 'Permission denied',
                    'message' => 'فشل في تعديل البيانات الشخصية'
                ]);
                throw new \Exception(message: 'فشل في تعديل البيانات الشخصية', code: 403);
            }

            // Update profile info using repository
            return $this->employeeRepository->updateEmployeeProfileInfo($employeeId, $effectiveCompanyId, $profileData);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::updateEmployeeProfileInfo failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'message' => 'فشل في تعديل البيانات الشخصية'
            ]);

            throw new \Exception(message: 'فشل في تعديل البيانات الشخصية', code: 500);
        }
    }

    /**
     * Update employee CV (bio and experience)
     */
    public function updateEmployeeCV(User $user, int $employeeId, array $cvData): bool
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);

            if (!$employee) {
                Log::warning('Employee not found', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'error' => 'Employee not found',
                    'message' => 'فشل في تعديل السيرة الذاتية و الخبرة'
                ]);
                throw new \Exception(message: 'فشل في تعديل السيرة الذاتية و الخبرة', code: 404);
            }

            // Check if user can modify this employee
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::warning('Permission denied for CV update', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'error' => 'Permission denied',
                    'message' => 'ليس لديك الصلاحية لتعديل السيرة الذاتية و الخبرة لهذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك الصلاحية لتعديل السيرة الذاتية و الخبرة لهذا الموظف', code: 403);
            }

            // Convert Arabic experience label to enum value if provided
            if (isset($cvData['experience'])) {
                $experienceValue = null;
                foreach (ExperienceLevel::cases() as $level) {
                    if ($level->getArabicLabel() === $cvData['experience']) {
                        $experienceValue = $level->value;
                        break;
                    }
                }

                if ($experienceValue !== null) {
                    $cvData['experience'] = $experienceValue;
                } else {
                    Log::warning('Invalid experience value provided', [
                        'provided_value' => $cvData['experience'],
                        'employee_id' => $employeeId,
                        'error' => 'Invalid experience value',
                        'message' => 'فشل في تعديل السيرة الذاتية و الخبرة'
                    ]);
                    throw new \Exception(message: 'فشل في تعديل السيرة الذاتية و الخبرة', code: 400);
                }
            }

            // Update CV using repository
            return $this->employeeRepository->updateEmployeeCV($employeeId, $cvData);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::updateEmployeeCV failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'message' => 'فشل في تعديل السيرة الذاتية و الخبرة'
            ]);

            throw new \Exception(message: 'فشل في تعديل السيرة الذاتية و الخبرة', code: 500);
        }
    }

    /**
     * Update employee social links
     */
    public function updateEmployeeSocialLinks(User $user, int $employeeId, array $socialData): bool
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);

            if (!$employee) {
                Log::warning('Employee not found', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'error' => 'Employee not found',
                    'message' => 'فشل في تعديل بيانات مواقع التواصل الاجتماعى'
                ]);
                throw new \Exception(message: 'فشل في تعديل بيانات مواقع التواصل الاجتماعى', code: 404);
            }

            // Check if user can modify this employee
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::warning('Permission denied for social links update', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'error' => 'Permission denied',
                    'message' => 'فشل في تعديل بيانات مواقع التواصل الاجتماعى'
                ]);
                throw new \Exception(message: 'فشل في تعديل بيانات مواقع التواصل الاجتماعى', code: 403);
            }

            // Update social links using repository
            return $this->employeeRepository->updateEmployeeSocialLinks($employeeId, $socialData);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::updateEmployeeSocialLinks failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'message' => 'فشل في تعديل بيانات مواقع التواصل الاجتماعى'
            ]);

            throw new \Exception(message: 'فشل في تعديل بيانات مواقع التواصل الاجتماعى', code: 500);
        }
    }

    /**
     * Update employee bank information
     */
    public function updateEmployeeBankInfo(User $user, int $employeeId, array $bankData): bool
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // First try to find employee in the effective company
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);

            // If not found and user is company owner (company_id = 0), try to find employee in any company they manage
            if (!$employee && $user->company_id == 0) {
                // For company owners, try to find the employee by ID without company filtering
                $employee = User::with('user_details')->where('user_id', $employeeId)->first();

                Log::info('Company owner looking for employee across companies', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'employee_found' => $employee ? true : false,
                    'employee_company_id' => $employee?->company_id,
                    'message' => 'بدء عملية البحث عن الموظف'
                ]);
            }

            if (!$employee) {
                Log::error('EmployeeManagementService::updateEmployeeBankInfo failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'effective_company_id' => $effectiveCompanyId,
                    'user_company_id' => $user->company_id,
                    'error' => 'Employee not found',
                    'message' => 'فشل في تعديل البيانات البنكيه'
                ]);
                throw new \Exception(message: 'فشل في تعديل البيانات البنكيه', code: 404);
            }

            // Check if user can modify this employee
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::updateEmployeeBankInfo failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'employee_company_id' => $employee->company_id,
                    'error' => 'Permission denied',
                    'message' => 'فشل في تعديل البيانات البنكيه'
                ]);
                throw new \Exception(message: 'فشل في تعديل البيانات البنكيه', code: 403);
            }

            Log::info('EmployeeManagementService::updateEmployeeBankInfo proceeding', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'employee_company_id' => $employee->company_id,
                'bank_data' => $bankData,
                'message' => 'بدء عملية تعديل البيانات البنكيه'
            ]);

            // Update bank info using repository
            return $this->employeeRepository->updateEmployeeBankInfo($employeeId, $bankData);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::updateEmployeeBankInfo failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'message' => 'فشل في تعديل البيانات البنكيه'
            ]);

            throw new \Exception('فشل في تعديل البيانات البنكيه', code: 500);
        }
    }

    /**
     * Add employee family data
     */
    public function addEmployeeFamilyData(User $user, int $employeeId, array $familyData): bool
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);

            if (!$employee) {
                Log::error('', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'error' => 'Employee not found',
                    'message' => 'فشل في إضافة البيانات العائلية'
                ]);
                throw new \Exception(message: 'فشل في إضافة البيانات العائلية', code: 404);
            }

            // Check if user can modify this employee
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'error' => 'Employee not found',
                    'message' => 'فشل في إضافة البيانات العائلية'
                ]);
                throw new \Exception(message: 'فشل في إضافة البيانات العائلية', code: 403);
            }

            // Update family data using repository
            return $this->employeeRepository->addEmployeeFamilyData($employeeId, $familyData);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::addEmployeeFamilyData failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'message' => 'فشل في إضافة البيانات العائلية'
            ]);

            throw new \Exception(message: 'فشل في إضافة البيانات العائلية', code: 500);
        }
    }

    /**
     * Delete employee family data
     */
    public function deleteEmployeeFamilyData(User $user, int $employeeId, int $contactId): bool
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);

            if (!$employee) {
                Log::error('', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'error' => 'Employee not found',
                    'message' => 'فشل في حذف البيانات العائلية'
                ]);
                throw new \Exception(message: 'فشل في حذف البيانات العائلية', code: 404);
            }

            // Check if user can modify this employee
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'error' => 'Permission denied',
                    'message' => 'ليس لديك صلاحية لحذف هذه البيانات'
                ]);
                throw new \Exception(message: 'ليس لديك صلاحية لحذف هذه البيانات', code: 403);
            }

            // Verify the contact belongs to the employee
            $contact = DB::table('ci_erp_employee_contacts')
                ->where('contact_id', $contactId)
                ->where('user_id', $employeeId)
                ->first();

            if (!$contact) {
                Log::error('', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'contact_id' => $contactId,
                    'error' => 'Contact not found',
                    'message' => 'بيانات العائلة غير موجودة'
                ]);
                throw new \Exception(message: 'بيانات العائلة غير موجودة', code: 404);
            }

            // Delete family data using repository
            return $this->employeeRepository->deleteEmployeeFamilyData($contactId);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::deleteEmployeeFamilyData failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'contact_id' => $contactId,
                'error' => $e->getMessage(),
                'message' => 'فشل في حذف البيانات العائلية'
            ]);

            throw new \Exception(message: 'فشل في حذف البيانات العائلية', code: 500);
        }
    }

    /**
     * Get employee documents with optional search
     */
    public function getEmployeeDocuments(User $user, int $employeeId, ?string $search = null): \Illuminate\Support\Collection
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);

            if (!$employee) {
                Log::error('EmployeeManagementService::getEmployeeDocuments failed - Employee not found', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'الموظف غير موجود'
                ]);
                throw new \Exception(message: 'الموظف غير موجود', code: 404);
            }

            // Check permissions (should be able to view requests of this employee)
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::getEmployeeDocuments failed - Permission denied', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحية لعرض مستندات هذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك صلاحية لعرض مستندات هذا الموظف', code: 403);
            }

            return $this->employeeRepository->getEmployeeDocuments($employeeId, $search);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getEmployeeDocuments failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'search' => $search,
                'error' => $e->getMessage(),
                'message' => 'فشل في عرض مستندات هذا الموظف'
            ]);
            throw new \Exception(message: 'فشل في عرض مستندات هذا الموظف', code: 500);
        }
    }

    /**
     * Update employee basic information
     */
    public function updateEmployeeBasicInfo(User $user, int $employeeId, array $data): bool
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);

            if (!$employee) {
                // Check if it's the owner themselves (company_id 0)
                if ($user->user_id === $employeeId && $user->company_id === 0) {
                    $employee = $user;
                } else {
                    Log::error('EmployeeManagementService::getEmployeeDocuments failed', [
                        'user_id' => $user->user_id,
                        'employee_id' => $employeeId,
                        'message' => 'الموظف غير موجود أو ليس في شركتك'
                    ]);
                    throw new \Exception(message: 'الموظف غير موجود أو ليس في شركتك', code: 404);
                }
            }

            // Check authorization (self or manager)
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::getEmployeeDocuments failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحية لتعديل بيانات هذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك صلاحية لتعديل بيانات هذا الموظف', code: 403);
            }

            // Split data into user table and details table
            $userData = array_intersect_key($data, array_flip([
                'first_name',
                'last_name',
                'contact_number',
                'country',
                'state',
                'city',
                'address_1',
                'address_2',
                'zipcode',
            ]));

            // Map gender if string provided
            if (isset($userData['gender'])) {
                if ($userData['gender'] === 'Male') {
                    $userData['gender'] = 1;
                } elseif ($userData['gender'] === 'Female') {
                    $userData['gender'] = 2;
                }
            }

            $detailsData = array_intersect_key($data, array_flip([
                'date_of_birth',
                'marital_status',
                'blood_group',
                'religion_id',
                'citizenship_id',
                'employee_id'
            ]));

            // Additional mapping if needed
            if (isset($data['id_number'])) {
                $detailsData['employee_idnum'] = $data['id_number'];
            }

            return $this->employeeRepository->updateEmployeeBasicInfo($employeeId, $userData, $detailsData);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::updateEmployeeBasicInfo failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'message' => 'فشل في تحديث بيانات هذا الموظف'
            ]);
            throw new \Exception(message: 'فشل في تحديث بيانات هذا الموظف', code: 500);
        }
    }

    /**
     * Get all profile related enums and types
     */
    public function getProfileEnums(): array
    {
        $user = Auth::user();
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
        return [
            'blood_groups' => \App\Enums\BloodGroupEnum::toArray(),
            'marital_statuses' => \App\Enums\MaritalStatusEnum::toArray(),
            'experience_levels' => \App\Enums\ExperienceLevel::toArray(),
            'relative_places' => \App\Enums\RelativePlace::toArray(),
            'relative_relations' => \App\Enums\RelativeRelation::toArray(),
            'job_types' => \App\Enums\JobTypeEnum::toArray(),
            'banks' => DB::table('ci_employee_accounts')->where('company_id', $effectiveCompanyId)->select('account_id', 'account_name')->get(),
            'genders' => [
                ['value' => 1, 'label_ar' => 'ذكر', 'label_en' => 'Male'],
                ['value' => 2, 'label_ar' => 'أنثى', 'label_en' => 'Female'],
            ],
            'religions' => ErpConstant::getReligions($effectiveCompanyId),
            'salay_type' => [
                ['value' => 1, 'label_ar' => 'فى الشهر', 'label_en' => 'Month'],
                ['value' => 2, 'label_ar' => 'فى الساعة', 'label_en' => 'Hour'],
            ],
            'salary_payment_method' => [
                ['value' => 'CASH', 'label_ar' => 'كاش', 'label_en' => 'Cash'],
                ['value' => 'DEPOSIT', 'label_ar' => 'ايداع', 'label_en' => 'Deposit'],
            ],
        ];
    }


    /**
     * Get employee contract data
     */
    public function getEmployeeContractData(User $user, int $employeeId): array
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // Fetch employee with company check
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);

            if (!$employee) {
                // Check if it's the owner themselves (company_id 0)
                if ($user->user_id === $employeeId && $user->company_id === 0) {
                    $employee = $user;
                } else {
                    Log::error('EmployeeManagementService::getEmployeeContractData failed', [
                        'user_id' => $user->user_id,
                        'employee_id' => $employeeId,
                        'message' => 'الموظف غير موجود أو ليس في شركتك'
                    ]);
                    throw new \Exception(message: 'الموظف غير موجود أو ليس في شركتك', code: 404);
                }
            }

            // Check authorization (self or manager)
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::getEmployeeContractData failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحية لعرض بيانات عقد هذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك صلاحية لعرض بيانات عقد هذا الموظف', code: 403);
            }

            return $this->employeeRepository->getEmployeeContractData($employeeId);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getEmployeeContractData failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'message' => 'فشل في عرض بيانات عقد هذا الموظف'
            ]);
            throw new \Exception(message: 'فشل في عرض بيانات عقد هذا الموظف', code: 500);
        }
    }
    /**
     * Update employee contract data
     */
    public function updateEmployeeContractData(User $user, int $employeeId, array $data): bool
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // Fetch employee with company check
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);

            if (!$employee) {
                // Check if it's the owner themselves (company_id 0)
                if ($user->user_id === $employeeId && $user->company_id === 0) {
                    $employee = $user;
                } else {
                    Log::error('EmployeeManagementService::getEmployeeContractData failed', [
                        'user_id' => $user->user_id,
                        'employee_id' => $employeeId,
                        'message' => 'الموظف غير موجود أو ليس في شركتك'
                    ]);
                    throw new \Exception(message: 'الموظف غير موجود أو ليس في شركتك', code: 404);
                }
            }

            // Check authorization (Must have permission to edit employees)
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::updateEmployeeContractData failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحية لتعديل بيانات عقد هذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك صلاحية لتعديل بيانات عقد هذا الموظف', code: 403);
            }
            $success = $this->employeeRepository->updateEmployeeContractData($employeeId, $data);
            if (!$success) {
                Log::error('EmployeeManagementService::updateEmployeeContractData failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'فشل تحديث بيانات العقد'
                ]);
                throw new \Exception(message: 'فشل تحديث بيانات العقد', code: 500);
            }

            Log::info('EmployeeManagementService::updateEmployeeContractData success', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'message' => 'تم تحديث بيانات العقد بنجاح'
            ]);
            return $success;
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::updateEmployeeContractData failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'message' => 'فشل تحديث بيانات العقد'
            ]);
            throw new \Exception(message: 'فشل تحديث بيانات العقد', code: 500);
        }
    }

    /**
     * Get available contract options for the user's company
     */
    public function getContractOptions(User $user): array
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            return $this->employeeRepository->getContractOptions($effectiveCompanyId);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getContractOptions failed', [
                'user_id' => $user->user_id,
                'error' => $e->getMessage(),
                'message' => 'فشل في الحصول على خيارات العقود'
            ]);
            throw new \Exception(message: 'فشل في الحصول على خيارات العقود', code: 500);
        }
    }

    public function addAllowance(User $user, int $employeeId, array $data): int
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);
            if (!$employee) {
                Log::error('EmployeeManagementService::addAllowance failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'الموظف غير موجود'
                ]);
                throw new \Exception(message: 'الموظف غير موجود', code: 404);
            }

            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::addAllowance failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحية لإضافة بدل لهذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك صلاحية لإضافة بدل لهذا الموظف', code: 403);
            }

            // Check if allowance already exists
            if ($this->employeeRepository->allowanceExists($employeeId, $data['pay_title'])) {
                Log::error('EmployeeManagementService::addAllowance failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'البدل موجود بالفعل'
                ]);
                throw new \Exception(message: 'البدل موجود بالفعل', code: 409);
            }

            Log::info('EmployeeManagementService::addAllowance', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'data' => $data,
                'message' => 'تم إضافة البدل بنجاح'
            ]);
            $data['company_id'] = $effectiveCompanyId;
            return $this->employeeRepository->addAllowance($employeeId, $data);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::addAllowance failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'message' => 'فشل إضافة البدل',
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل إضافة البدل', code: 500);
        }
    }

    public function addCommission(User $user, int $employeeId, array $data): int
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);
            if (!$employee) {
                Log::error('EmployeeManagementService::addCommission failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'الموظف غير موجود'
                ]);
                throw new \Exception(message: 'الموظف غير موجود', code: 404);
            }
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::addCommission failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحية لإضافة عمولة لهذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك صلاحية لإضافة عمولة لهذا الموظف', code: 403);
            }

            // Check if commission already exists
            if ($this->employeeRepository->commissionExists($employeeId, $data['pay_title'])) {
                Log::error('EmployeeManagementService::addCommission failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'العمولة موجودة بالفعل',
                ]);
                throw new \Exception(message: 'العمولة موجودة بالفعل', code: 409);
            }

            Log::info('EmployeeManagementService::addCommission', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'data' => $data,
                'message' => 'تم إضافة العمولة بنجاح'
            ]);
            $data['company_id'] = $effectiveCompanyId;
            return $this->employeeRepository->addCommission($employeeId, $data);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::addCommission failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'message' => 'فشل إضافة العمولة',
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل إضافة العمولة', code: 500);
        }
    }

    public function addStatutoryDeduction(User $user, int $employeeId, array $data): int
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);
            if (!$employee) {
                Log::error('EmployeeManagementService::addStatutoryDeduction failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'الموظف غير موجود',
                ]);
                throw new \Exception(message: 'الموظف غير موجود', code: 404);
            }
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::addStatutoryDeduction failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحية لإضافة خصم لهذا الموظف',
                ]);
                throw new \Exception(message: 'ليس لديك صلاحية لإضافة خصم لهذا الموظف', code: 403);
            }

            // Check if statutory deduction already exists
            if ($this->employeeRepository->statutoryDeductionExists($employeeId, $data['pay_title'])) {
                Log::error('EmployeeManagementService::addStatutoryDeduction failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'الخصم القانوني موجود بالفعل',
                ]);
                throw new \Exception(message: 'الخصم القانوني موجود بالفعل', code: 409);
            }

            Log::info('EmployeeManagementService::addStatutoryDeduction', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'data' => $data,
                'message' => 'تم إضافة الخصم القانوني بنجاح'
            ]);
            $data['company_id'] = $effectiveCompanyId;
            return $this->employeeRepository->addStatutoryDeduction($employeeId, $data);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::addStatutoryDeduction failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'message' => 'فشل إضافة الخصم القانوني',
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل إضافة الخصم القانوني', code: 500);
        }
    }

    public function addOtherPayment(User $user, int $employeeId, array $data): int
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);
            if (!$employee) {
                Log::error('EmployeeManagementService::addOtherPayment failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'الموظف غير موجود'
                ]);
                throw new \Exception(message: 'الموظف غير موجود', code: 404);
            }
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::addOtherPayment failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحية لإضافة تعويض لهذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك صلاحية لإضافة تعويض لهذا الموظف', code: 403);
            }

            // Check if other payment already exists
            if ($this->employeeRepository->otherPaymentExists($employeeId, $data['pay_title'])) {
                Log::error('EmployeeManagementService::addOtherPayment failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'التعويض موجود بالفعل'
                ]);
                throw new \Exception(message: 'التعويض موجود بالفعل', code: 409);
            }

            Log::info('EmployeeManagementService::addOtherPayment', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'data' => $data,
                'message' => 'تم إضافة التعويض بنجاح'
            ]);
            $data['company_id'] = $effectiveCompanyId;
            return $this->employeeRepository->addOtherPayment($employeeId, $data);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::addOtherPayment failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'message' => 'فشل إضافة التعويض',
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل إضافة التعويض', code: 500);
        }
    }

    // ==================== Update Contract Components ====================

    public function updateAllowance(User $user, int $employeeId, int $id, array $data): bool
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);
            if (!$employee) {
                Log::error('EmployeeManagementService::updateAllowance failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'الموظف غير موجود'
                ]);
                throw new \Exception(message: 'الموظف غير موجود', code: 404);
            }
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::updateAllowance failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحية لتعديل بدل هذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك صلاحية لتعديل بدل هذا الموظف', code: 403);
            }

            $allowance = $this->employeeRepository->getAllowanceById($id);
            if (!$allowance || $allowance->staff_id !== $employeeId) {
                Log::error('EmployeeManagementService::updateAllowance failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'البدل غير موجود'
                ]);
                throw new \Exception(message: 'البدل غير موجود', code: 404);
            }

            Log::info('EmployeeManagementService::updateAllowance', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'id' => $id,
                'data' => $data,
                'message' => 'تم تعديل بدل هذا الموظف بنجاح'
            ]);
            return $this->employeeRepository->updateAllowance($id, $data);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::updateAllowance failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل تعديل بدل هذا الموظف', code: 500);
        }
    }

    public function deleteAllowance(User $user, int $employeeId, int $id): bool
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);
            if (!$employee) {
                Log::error('EmployeeManagementService::deleteAllowance failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'الموظف غير موجود'
                ]);
                throw new \Exception(message: 'الموظف غير موجود', code: 404);
            }
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::deleteAllowance failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحية لحذف بدل هذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك صلاحية لحذف بدل هذا الموظف', code: 403);
            }

            $allowance = $this->employeeRepository->getAllowanceById($id);
            if (!$allowance || $allowance->staff_id !== $employeeId) {
                Log::error('EmployeeManagementService::deleteAllowance failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'البدل غير موجود'
                ]);
                throw new \Exception(message: 'البدل غير موجود', code: 404);
            }

            Log::info('EmployeeManagementService::deleteAllowance', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'id' => $id,
                'message' => 'تم حذف بدل هذا الموظف بنجاح'
            ]);
            return $this->employeeRepository->deleteAllowance($id);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::deleteAllowance failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل حذف بدل هذا الموظف', code: 500);
        }
    }

    public function updateCommission(User $user, int $employeeId, int $id, array $data): bool
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);
            if (!$employee) {
                Log::error('EmployeeManagementService::updateCommission failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'الموظف غير موجود'
                ]);
                throw new \Exception(message: 'الموظف غير موجود', code: 404);
            }
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::updateCommission failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحية لتعديل عمولة هذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك صلاحية لتعديل عمولة هذا الموظف', code: 403);
            }

            $commission = $this->employeeRepository->getCommissionById($id);
            if (!$commission || $commission->staff_id !== $employeeId) {
                Log::error('EmployeeManagementService::updateCommission failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'العمولة غير موجودة'
                ]);
                throw new \Exception(message: 'العمولة غير موجودة', code: 404);
            }

            Log::info('EmployeeManagementService::updateCommission', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'id' => $id,
                'data' => $data,
                'message' => 'تم تعديل عمولة هذا الموظف بنجاح'
            ]);
            return $this->employeeRepository->updateCommission($id, $data);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::updateCommission failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل تعديل عمولة هذا الموظف', code: 500);
        }
    }

    public function deleteCommission(User $user, int $employeeId, int $id): bool
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);
            if (!$employee) {
                Log::error('EmployeeManagementService::deleteCommission failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'الموظف غير موجود'
                ]);
                throw new \Exception(message: 'الموظف غير موجود', code: 404);
            }
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::deleteCommission failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحية لحذف عمولة هذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك صلاحية لحذف عمولة هذا الموظف', code: 403);
            }

            $commission = $this->employeeRepository->getCommissionById($id);
            if (!$commission || $commission->staff_id !== $employeeId) {
                Log::error('EmployeeManagementService::deleteCommission failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'العمولة غير موجودة'
                ]);
                throw new \Exception(message: 'العمولة غير موجودة', code: 404);
            }

            Log::info('EmployeeManagementService::deleteCommission', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'id' => $id,
                'message' => 'تم حذف عمولة هذا الموظف بنجاح'
            ]);
            return $this->employeeRepository->deleteCommission($id);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::deleteCommission failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل حذف عمولة هذا الموظف', code: 500);
        }
    }

    public function updateStatutoryDeduction(User $user, int $employeeId, int $id, array $data): bool
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);
            if (!$employee) {
                Log::error('EmployeeManagementService::updateStatutoryDeduction failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'الموظف غير موجود'
                ]);
                throw new \Exception(message: 'الموظف غير موجود', code: 404);
            }
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::updateStatutoryDeduction failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحية لتعديل خصم هذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك صلاحية لتعديل خصم هذا الموظف', code: 403);
            }

            $deduction = $this->employeeRepository->getStatutoryDeductionById($id);
            if (!$deduction || $deduction->staff_id !== $employeeId) {
                Log::error('EmployeeManagementService::updateStatutoryDeduction failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'الخصم غير موجود'
                ]);
                throw new \Exception(message: 'الخصم غير موجود', code: 404);
            }

            Log::info('EmployeeManagementService::updateStatutoryDeduction', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'id' => $id,
                'data' => $data,
                'message' => 'تم تعديل خصم هذا الموظف بنجاح'
            ]);
            return $this->employeeRepository->updateStatutoryDeduction($id, $data);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::updateStatutoryDeduction failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل تعديل خصم هذا الموظف', code: 500);
        }
    }

    public function deleteStatutoryDeduction(User $user, int $employeeId, int $id): bool
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);
            if (!$employee) {
                Log::error('EmployeeManagementService::deleteStatutoryDeduction failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'الموظف غير موجود'
                ]);
                throw new \Exception(message: 'الموظف غير موجود', code: 404);
            }
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::deleteStatutoryDeduction failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحية لحذف خصم هذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك صلاحية لحذف خصم هذا الموظف', code: 403);
            }

            $deduction = $this->employeeRepository->getStatutoryDeductionById($id);
            if (!$deduction || $deduction->staff_id !== $employeeId) {
                Log::error('EmployeeManagementService::deleteStatutoryDeduction failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'الخصم غير موجود'
                ]);
                throw new \Exception(message: 'الخصم غير موجود', code: 404);
            }

            Log::info('EmployeeManagementService::deleteStatutoryDeduction', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'id' => $id,
                'message' => 'تم حذف خصم هذا الموظف بنجاح'
            ]);
            return $this->employeeRepository->deleteStatutoryDeduction($id);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::deleteStatutoryDeduction failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل حذف خصم هذا الموظف', code: 500);
        }
    }

    public function updateOtherPayment(User $user, int $employeeId, int $id, array $data): bool
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);
            if (!$employee) {
                Log::error('EmployeeManagementService::updateOtherPayment failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'الموظف غير موجود'
                ]);
                throw new \Exception(message: 'الموظف غير موجود', code: 404);
            }
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::updateOtherPayment failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحية لتعديل دفع هذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك صلاحية لتعديل دفع هذا الموظف', code: 403);
            }

            $payment = $this->employeeRepository->getOtherPaymentById($id);
            if (!$payment || $payment->staff_id !== $employeeId) {
                Log::error('EmployeeManagementService::updateOtherPayment failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'التعويض غير موجود'
                ]);
                throw new \Exception(message: 'التعويض غير موجود', code: 404);
            }

            Log::info('EmployeeManagementService::updateOtherPayment', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'id' => $id,
                'data' => $data,
                'message' => 'تم تعديل دفع هذا الموظف بنجاح'
            ]);
            return $this->employeeRepository->updateOtherPayment($id, $data);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::updateOtherPayment failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل تعديل دفع هذا الموظف', code: 500);
        }
    }

    public function deleteOtherPayment(User $user, int $employeeId, int $id): bool
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);
            if (!$employee) {
                Log::error('EmployeeManagementService::deleteOtherPayment failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'الموظف غير موجود'
                ]);
                throw new \Exception(message: 'الموظف غير موجود', code: 404);
            }
            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::deleteOtherPayment failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحية لحذف دفع هذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك صلاحية لحذف دفع هذا الموظف', code: 403);
            }

            $payment = $this->employeeRepository->getOtherPaymentById($id);
            if (!$payment || $payment->staff_id !== $employeeId) {
                Log::error('EmployeeManagementService::deleteOtherPayment failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'التعويض غير موجود'
                ]);
                throw new \Exception(message: 'التعويض غير موجود', code: 404);
            }

            Log::info('EmployeeManagementService::deleteOtherPayment', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'id' => $id,
                'message' => 'تم حذف دفع هذا الموظف بنجاح'
            ]);
            return $this->employeeRepository->deleteOtherPayment($id);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::deleteOtherPayment failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل حذف دفع هذا الموظف', code: 500);
        }
    }

    public function getAllowances(User $user, int $employeeId, ?string $search = null): array
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);
            if (!$employee) {
                Log::error('EmployeeManagementService::getAllowances failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'الموظف غير موجود'
                ]);
                throw new \Exception(message: 'الموظف غير موجود', code: 404);
            }

            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::getAllowances failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحية لعرض بيانات هذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك صلاحية لعرض بيانات هذا الموظف', code: 403);
            }

            Log::info('EmployeeManagementService::getAllowances', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'message' => 'تم عرض بيانات هذا الموظف بنجاح'
            ]);
            return $this->employeeRepository->getAllowances($employeeId, $search);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getAllowances failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل عرض بيانات هذا الموظف', code: 500);
        }
    }

    public function getCommissions(User $user, int $employeeId, ?string $search = null): array
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);
            if (!$employee) {
                Log::error('EmployeeManagementService::getCommissions failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'الموظف غير موجود'
                ]);
                throw new \Exception(message: 'الموظف غير موجود', code: 404);
            }

            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::getCommissions failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحية لعرض بيانات هذا الموظف'
                ]); 
                throw new \Exception(message: 'ليس لديك صلاحية لعرض بيانات هذا الموظف', code: 403);
            }

            Log::info('EmployeeManagementService::getCommissions', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'message' => 'تم عرض بيانات هذا الموظف بنجاح'
            ]);
            return $this->employeeRepository->getCommissions($employeeId, $search);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getCommissions failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'message' => 'فشل عرض بيانات هذا الموظف'
            ]);
            throw new \Exception(message: 'فشل عرض بيانات هذا الموظف', code: 500);
        }
    }

    public function getStatutoryDeductions(User $user, int $employeeId, ?string $search = null): array
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);
            if (!$employee) {
                Log::error('EmployeeManagementService::getStatutoryDeductions failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'الموظف غير موجود'
                ]);
                throw new \Exception(message: 'الموظف غير موجود', code: 404);
            }

            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::getStatutoryDeductions failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحية لعرض بيانات هذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك صلاحية لعرض بيانات هذا الموظف', code: 403);
            }

            Log::info('EmployeeManagementService::getStatutoryDeductions', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'message' => 'تم عرض بيانات هذا الموظف بنجاح'
            ]);
            return $this->employeeRepository->getStatutoryDeductions($employeeId, $search);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getStatutoryDeductions failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'message' => 'فشل عرض بيانات هذا الموظف'
            ]);
            throw new \Exception(message: 'فشل عرض بيانات هذا الموظف', code: 500);
        }
    }

    public function getOtherPayments(User $user, int $employeeId, ?string $search = null): array
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $employee = $this->employeeRepository->getEmployeeWithDetails($employeeId, $effectiveCompanyId);
            if (!$employee) {
                Log::error('EmployeeManagementService::getOtherPayments failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'الموظف غير موجود'
                ]);
                throw new \Exception(message: 'الموظف غير موجود', code: 404);
            }

            if (!$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::error('EmployeeManagementService::getOtherPayments failed', [
                    'user_id' => $user->user_id,
                    'employee_id' => $employeeId,
                    'message' => 'ليس لديك صلاحية لعرض بيانات هذا الموظف'
                ]);
                throw new \Exception(message: 'ليس لديك صلاحية لعرض بيانات هذا الموظف', code: 403);
            }

            Log::info('EmployeeManagementService::getOtherPayments', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'message' => 'تم عرض بيانات هذا الموظف بنجاح'
            ]);
            return $this->employeeRepository->getOtherPayments($employeeId, $search);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getOtherPayments failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'message' => 'فشل عرض بيانات هذا الموظف'
            ]);
            throw new \Exception(message: 'فشل عرض بيانات هذا الموظف', code: 500);
        }
    }

    /**
     * Get employee counts grouped by country for the company
     * 
     * @param User $user
     * @return array
     */
    public function getEmployeeCountryStats(User $user): array
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            return $this->employeeRepository->getEmployeeCountByCountry($effectiveCompanyId);
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getEmployeeCountryStats failed', [
                'user_id' => $user->user_id,
                'error' => $e->getMessage(),
                'message' => 'حدث خطأ أثناء جلب إحصائيات الموظفين حسب الدولة'
            ]);
            throw new \Exception(message: 'حدث خطأ أثناء جلب إحصائيات الموظفين حسب الدولة', code: 500);
        }
    }
}
