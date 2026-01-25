<?php

namespace App\Services;

use App\Models\User;
use App\DTOs\Employee\EmployeeFilterDTO;
use App\DTOs\Employee\CreateEmployeeDTO;
use App\DTOs\Employee\UpdateEmployeeDTO;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Employee Management Service
 * 
 * Handles employee operations with hierarchy and permission checks
 * Uses existing SimplePermissionService for all permission logic
 */
class EmployeeManagementService
{
    public function __construct(
        private SimplePermissionService $permissionService
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
                'error' => $e->getMessage()
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
            $employee = User::with([
                'user_details.designation',
                'user_details.department',
                'user_details.branch'
            ])->find($employeeId);
            
            if (!$employee) {
                return null;
            }
            
            // Check if user can access this employee
            if (!$this->permissionService->canAccessEmployee($user, $employee)) {
                return null;
            }
            
            return $employee;
            
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getEmployeeDetails failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);
            
            return null;
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
            // Check permission to create employees
            if (!$this->permissionService->checkPermission($user, 'employee.create')) {
                return null;
            }
            
            // Check company access
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            
            DB::beginTransaction();
            
            // Create user record
            $employee = User::create([
                'first_name' => $data->first_name,
                'last_name' => $data->last_name,
                'username' => $data->username,
                'email' => $data->email,
                'contact_number' => $data->contact_number,
                'company_id' => $companyId,
                'user_type' => 'staff',
                'is_active' => $data->is_active,
                'user_role_id' => 1, // Default role
                'password' => bcrypt($data->password),
                'profile_photo' => '', // Default empty profile photo
                'gender' => $data->gender ?? 'M', // Default gender if not provided
                'created_at' => now()->format('Y-m-d H:i:s'), // Manual timestamp since timestamps are disabled
            ]);
            
            // Create user details
            $employee->user_details()->create([
                'company_id' => $companyId,
                'designation_id' => $data->designation_id,
                'department_id' => $data->department_id,
                'branch_id' => $data->branch_id,
                'date_of_joining' => $data->date_of_joining ?? now()->format('Y-m-d'),
                'basic_salary' => $data->basic_salary ?? 0,
                'employee_id' => $data->employee_idnum ?? 'EMP' . str_pad($employee->user_id, 4, '0', STR_PAD_LEFT),
                'reporting_manager' => 0, // Default no reporting manager
                'office_shift_id' => 1, // Default shift
                'hourly_rate' => 0, // Default hourly rate
                'salay_type' => 1, // Default salary type (monthly)
                'bank_name' => 0, // Default no bank
                'ml_tax_category' => 1, // Default tax category
                'ml_eis_contribution' => 0, // Default EIS contribution
                'ml_socso_category' => 1, // Default SOCSO category
                'ml_hrdf' => 0, // Default HRDF
                'job_type' => 1, // Default job type (full-time)
                'date_of_birth' => $data->date_of_birth,
            ]);
            
            DB::commit();
            
            // Clear permissions cache for the new employee
            $this->permissionService->clearUserPermissionsCache($employee->user_id);
            
            return $employee->load(['user_details.designation', 'user_details.department']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('EmployeeManagementService::createEmployee failed', [
                'user_id' => $user->user_id,
                'data' => $data->toArray(),
                'error' => $e->getMessage()
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
                return null;
            }
            
            // Check if user can edit this employee
            if (!$this->canEditEmployee($user, $employee)) {
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
                'error' => $e->getMessage()
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
                return false;
            }
            
            // Check if user can delete/deactivate this employee
            if (!$this->canDeleteEmployee($user, $employee)) {
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
                'error' => $e->getMessage()
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
            $baseQuery = $this->buildEmployeesQuery($user, new EmployeeFilterDTO());
            
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
                'error' => $e->getMessage()
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
                'error' => $e->getMessage()
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
            
            $stats = [
                'total_employees' => 0,
                'active_employees' => 0,
                'inactive_employees' => 0,
                'departments_count' => 0,
                'designations_count' => 0,
                'average_salary' => 0,
                'total_salary_cost' => 0,
                'employees_by_department' => [],
                'employees_by_designation' => [],
                'employees_by_hierarchy' => [],
                'by_gender' => [],
                'by_age_group' => [],
                'salary_statistics' => [],
                'recent_hires' => 0,
            ];
            
            // Base query for accessible employees
            $baseQuery = User::where('ci_erp_users.company_id', $companyId)
                           ->where('ci_erp_users.user_type', 'staff');
            
            // Apply hierarchy filtering if not company owner
            if (!$this->permissionService->isCompanyOwner($user)) {
                $baseQuery = $this->permissionService->filterSubordinates($baseQuery, $user);
            }
            
            // Total counts
            $stats['total_employees'] = $baseQuery->count();
            $stats['active_employees'] = (clone $baseQuery)->where('is_active', 1)->count();
            $stats['inactive_employees'] = $stats['total_employees'] - $stats['active_employees'];
            
            // Departments and designations count
            $stats['departments_count'] = (clone $baseQuery)
                ->join('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
                ->distinct('ci_erp_users_details.department_id')
                ->whereNotNull('ci_erp_users_details.department_id')
                ->count('ci_erp_users_details.department_id');
                
            $stats['designations_count'] = (clone $baseQuery)
                ->join('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
                ->distinct('ci_erp_users_details.designation_id')
                ->whereNotNull('ci_erp_users_details.designation_id')
                ->count('ci_erp_users_details.designation_id');
            
            // Salary statistics (only for authorized users)
            if ($this->permissionService->canViewSalaries($user)) {
                $salaryStats = (clone $baseQuery)
                    ->join('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
                    ->where('ci_erp_users_details.basic_salary', '>', 0)
                    ->selectRaw('
                        AVG(ci_erp_users_details.basic_salary) as average_salary,
                        SUM(ci_erp_users_details.basic_salary) as total_salary,
                        MIN(ci_erp_users_details.basic_salary) as min_salary,
                        MAX(ci_erp_users_details.basic_salary) as max_salary,
                        COUNT(*) as employees_with_salary
                    ')
                    ->first();
                    
                $stats['average_salary'] = round($salaryStats->average_salary ?? 0, 2);
                $stats['total_salary_cost'] = round($salaryStats->total_salary ?? 0, 2);
                $stats['salary_statistics'] = [
                    'min_salary' => round($salaryStats->min_salary ?? 0, 2),
                    'max_salary' => round($salaryStats->max_salary ?? 0, 2),
                    'employees_with_salary' => $salaryStats->employees_with_salary ?? 0,
                ];
            }
            
            // By department with detailed info
            $departmentStats = (clone $baseQuery)
                ->join('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
                ->join('ci_departments', 'ci_erp_users_details.department_id', '=', 'ci_departments.department_id')
                ->select([
                    'ci_departments.department_id as dept_id',
                    'ci_departments.department_name',
                    DB::raw('COUNT(*) as total_count'),
                    DB::raw('SUM(CASE WHEN ci_erp_users.is_active = 1 THEN 1 ELSE 0 END) as active_count'),
                ])
                ->groupBy('ci_departments.department_id', 'ci_departments.department_name')
                ->get();
            
            foreach ($departmentStats as $dept) {
                $stats['employees_by_department'][] = [
                    'department_id' => $dept->dept_id ? (int)$dept->dept_id : null,
                    'department_name' => $dept->department_name,
                    'count' => (int)$dept->total_count,
                    'total_employees' => (int)$dept->total_count,
                    'active_employees' => (int)$dept->active_count,
                    'inactive_employees' => (int)($dept->total_count - $dept->active_count),
                ];
            }
            
            // By designation with detailed info
            $designationStats = DB::table('ci_erp_users')
                ->where('ci_erp_users.company_id', $companyId)
                ->where('ci_erp_users.user_type', 'staff')
                ->join('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
                ->join('ci_designations', 'ci_erp_users_details.designation_id', '=', 'ci_designations.designation_id')
                ->select([
                    'ci_designations.designation_id',
                    'ci_designations.designation_name',
                    'ci_designations.hierarchy_level',
                    DB::raw('COUNT(*) as total_count'),
                    DB::raw('SUM(CASE WHEN ci_erp_users.is_active = 1 THEN 1 ELSE 0 END) as active_count'),
                ])
                ->groupBy('ci_designations.designation_id', 'ci_designations.designation_name', 'ci_designations.hierarchy_level')
                ->get();
            
            foreach ($designationStats as $desig) {
                $stats['employees_by_designation'][] = [
                    'designation_id' => (int)$desig->designation_id,
                    'designation_name' => $desig->designation_name,
                    'hierarchy_level' => $desig->hierarchy_level !== null ? (int)$desig->hierarchy_level : null,
                    'count' => (int)$desig->total_count,
                    'total_employees' => (int)$desig->total_count,
                    'active_employees' => (int)$desig->active_count,
                    'inactive_employees' => (int)($desig->total_count - $desig->active_count),
                ];
            }
            
            // By hierarchy level
            $hierarchyStats = DB::table('ci_erp_users')
                ->where('ci_erp_users.company_id', $companyId)
                ->where('ci_erp_users.user_type', 'staff')
                ->join('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
                ->join('ci_designations', 'ci_erp_users_details.designation_id', '=', 'ci_designations.designation_id')
                ->select([
                    'ci_designations.hierarchy_level',
                    DB::raw('GROUP_CONCAT(DISTINCT ci_designations.designation_name SEPARATOR ", ") as level_names'),
                    DB::raw('COUNT(*) as total_count'),
                    DB::raw('SUM(CASE WHEN ci_erp_users.is_active = 1 THEN 1 ELSE 0 END) as active_count'),
                ])
                ->whereNotNull('ci_designations.hierarchy_level')
                ->groupBy('ci_designations.hierarchy_level')
                ->orderBy('ci_designations.hierarchy_level')
                ->get();
            
            foreach ($hierarchyStats as $stat) {
                $hierarchyLevel = (int)$stat->hierarchy_level;
                
                $stats['employees_by_hierarchy'][] = [
                    'hierarchy_level' => $hierarchyLevel,
                    'level_name' => $stat->level_names, // Use actual designation names from DB
                    'count' => (int)$stat->total_count,
                    'total_employees' => (int)$stat->total_count,
                    'active_employees' => (int)$stat->active_count,
                    'inactive_employees' => (int)($stat->total_count - $stat->active_count),
                ];
            }
            // By gender
            $genderStats = (clone $baseQuery)
                ->select([
                    'ci_erp_users.gender',
                    DB::raw('COUNT(*) as count')
                ])
                ->whereNotNull('ci_erp_users.gender')
                ->groupBy('ci_erp_users.gender')
                ->get();
            
            foreach ($genderStats as $gender) {
                // Handle different gender value formats
                $genderCode = $gender->gender;
                $genderName = 'غير محدد';
                
                // Map gender values to proper names
                if ($genderCode === 'M' || $genderCode === 'male' || $genderCode === '1') {
                    $genderCode = 'M';
                    $genderName = 'ذكر';
                } elseif ($genderCode === 'F' || $genderCode === 'female' || $genderCode === '2') {
                    $genderCode = 'F';
                    $genderName = 'أنثى';
                }
                
                $stats['by_gender'][] = [
                    'gender' => $genderCode,
                    'gender_name' => $genderName,
                    'count' => (int)$gender->count,
                ];
            }
            
            // By age group
            $ageStats = (clone $baseQuery)
                ->join('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
                ->whereNotNull('ci_erp_users_details.date_of_birth')
                ->selectRaw('
                    CASE 
                        WHEN TIMESTAMPDIFF(YEAR, ci_erp_users_details.date_of_birth, CURDATE()) < 25 THEN "تحت 25"
                        WHEN TIMESTAMPDIFF(YEAR, ci_erp_users_details.date_of_birth, CURDATE()) BETWEEN 25 AND 34 THEN "25-34"
                        WHEN TIMESTAMPDIFF(YEAR, ci_erp_users_details.date_of_birth, CURDATE()) BETWEEN 35 AND 44 THEN "35-44"
                        WHEN TIMESTAMPDIFF(YEAR, ci_erp_users_details.date_of_birth, CURDATE()) BETWEEN 45 AND 54 THEN "45-54"
                        ELSE "55 فأكثر"
                    END as age_group,
                    COUNT(*) as count
                ')
                ->groupBy('age_group')
                ->get();
            
            foreach ($ageStats as $age) {
                $stats['by_age_group'][] = [
                    'age_group' => $age->age_group,
                    'count' => (int)$age->count,
                ];
            }
            
            // Recent hires (last 30 days)
            $recentHires = (clone $baseQuery)
                ->join('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
                ->where('ci_erp_users_details.date_of_joining', '>=', now()->subDays(30))
                ->count();
            
            $stats['recent_hires'] = (int)$recentHires;
            
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
            $baseQuery = $this->buildEmployeesQuery($user, new EmployeeFilterDTO());
            
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

    /**
     * Check if editor can edit target employee
     * 
     * @param User $editor
     * @param User $target
     * @return bool
     */
    private function canEditEmployee(User $editor, User $target): bool
    {
        // Check basic permission
        if (!$this->permissionService->checkPermission($editor, 'employee.edit')) {
            return false;
        }
        
        // Use existing hierarchy logic from SimplePermissionService
        return $this->permissionService->canApproveEmployeeRequests($editor, $target);
    }

    /**
     * Check if user can delete employee (uses SimplePermissionService logic)
     * 
     * @param User $deleter
     * @param User $target
     * @return bool
     */
    private function canDeleteEmployee(User $deleter, User $target): bool
    {
        // Check basic permission
        if (!$this->permissionService->checkPermission($deleter, 'employee.delete')) {
            return false;
        }
        
        // Use existing hierarchy logic from SimplePermissionService
        return $this->permissionService->canApproveEmployeeRequests($deleter, $target);
    }

    /**
     * Get employee documents with permission check
     * 
     * @param User $user Current user requesting the data
     * @param int $employeeId Target employee ID
     * @return array|null
     */
    public function getEmployeeDocuments(User $user, int $employeeId): ?array
    {
        try {
            $employee = User::find($employeeId);
            
            if (!$employee) {
                return null;
            }
            
            // Check if user can access this employee
            if (!$this->permissionService->canAccessEmployee($user, $employee)) {
                return null;
            }
            
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            
            // Get real documents from database
            $documentsQuery = DB::table('ci_users_documents')
                ->where('company_id', $companyId)
                ->where('user_id', $employeeId)
                ->select([
                    'document_id as id',
                    'document_type',
                    'document_name as file_name',
                    'document_file as file_path',
                    'expiry_date',
                    'created_at'
                ])
                ->orderBy('created_at', 'desc');
            
            $realDocuments = $documentsQuery->get();
            
            $documents = [];
            
            if ($realDocuments->count() > 0) {
                // Use real documents from database
                foreach ($realDocuments as $doc) {
                    $documents[] = [
                        'id' => $doc->id,
                        'document_type' => $doc->document_type,
                        'file_name' => $doc->file_name,
                        'file_path' => $doc->file_path ? '/storage/documents/' . $doc->file_path : null,
                        'file_size' => 'غير محدد', // File size not stored in database
                        'expiry_date' => $doc->expiry_date,
                        'uploaded_at' => $doc->created_at,
                        'uploaded_by' => 'النظام', // Uploader not stored in database
                    ];
                }
            } else {
                // Return empty array if no documents found
                $documents = [];
            }
            
            return [
                'employee' => [
                    'id' => $employee->user_id,
                    'name' => $employee->first_name . ' ' . $employee->last_name,
                    'employee_id' => $employee->user_details->employee_id ?? 'EMP' . str_pad($employee->user_id, 4, '0', STR_PAD_LEFT)
                ],
                'documents' => $documents,
                'total' => count($documents),
                'summary' => [
                    'total_size' => count($documents) > 0 ? 'غير محدد' : '0 KB',
                    'document_types' => array_unique(array_column($documents, 'document_type'))
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getEmployeeDocuments failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

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
                return null;
            }
            
            // Check if user can access this employee
            if (!$this->permissionService->canAccessEmployee($user, $employee)) {
                return null;
            }
            
            // Mock leave balance data - في التطبيق الحقيقي ستأتي من قاعدة البيانات
            $currentYear = now()->year;
            
            $leaveTypes = [
                'annual_leave' => [
                    'name' => 'الإجازة السنوية',
                    'total' => 30,
                    'used' => rand(5, 20),
                    'pending' => rand(0, 3),
                    'remaining' => 0
                ],
                'sick_leave' => [
                    'name' => 'الإجازة المرضية',
                    'total' => 15,
                    'used' => rand(0, 8),
                    'pending' => rand(0, 2),
                    'remaining' => 0
                ],
                'emergency_leave' => [
                    'name' => 'الإجازة الطارئة',
                    'total' => 5,
                    'used' => rand(0, 3),
                    'pending' => rand(0, 1),
                    'remaining' => 0
                ],
                'maternity_leave' => [
                    'name' => 'إجازة الأمومة',
                    'total' => $employee->gender === 'F' ? 90 : 0,
                    'used' => $employee->gender === 'F' ? rand(0, 90) : 0,
                    'pending' => 0,
                    'remaining' => 0
                ],
                'paternity_leave' => [
                    'name' => 'إجازة الأبوة',
                    'total' => $employee->gender === 'M' ? 7 : 0,
                    'used' => $employee->gender === 'M' ? rand(0, 7) : 0,
                    'pending' => 0,
                    'remaining' => 0
                ]
            ];
            
            // Calculate remaining days for each leave type
            foreach ($leaveTypes as $key => &$leaveType) {
                $leaveType['remaining'] = max(0, $leaveType['total'] - $leaveType['used'] - $leaveType['pending']);
            }
            
            // Calculate totals
            $totalAllocated = array_sum(array_column($leaveTypes, 'total'));
            $totalUsed = array_sum(array_column($leaveTypes, 'used'));
            $totalPending = array_sum(array_column($leaveTypes, 'pending'));
            $totalRemaining = array_sum(array_column($leaveTypes, 'remaining'));
            
            return [
                'employee' => [
                    'id' => $employee->user_id,
                    'name' => $employee->first_name . ' ' . $employee->last_name,
                    'employee_id' => $employee->user_details->employee_id ?? 'EMP' . str_pad($employee->user_id, 4, '0', STR_PAD_LEFT)
                ],
                'year' => $currentYear,
                'leave_types' => $leaveTypes,
                'summary' => [
                    'total_allocated' => $totalAllocated,
                    'total_used' => $totalUsed,
                    'total_pending' => $totalPending,
                    'total_remaining' => $totalRemaining,
                    'utilization_rate' => $totalAllocated > 0 ? round(($totalUsed / $totalAllocated) * 100, 1) : 0
                ],
                'recent_leaves' => [
                    [
                        'type' => 'annual_leave',
                        'start_date' => now()->subDays(rand(10, 60))->format('Y-m-d'),
                        'end_date' => now()->subDays(rand(5, 9))->format('Y-m-d'),
                        'days' => rand(2, 7),
                        'status' => 'approved',
                        'reason' => 'إجازة شخصية'
                    ],
                    [
                        'type' => 'sick_leave',
                        'start_date' => now()->subDays(rand(80, 120))->format('Y-m-d'),
                        'end_date' => now()->subDays(rand(78, 79))->format('Y-m-d'),
                        'days' => rand(1, 3),
                        'status' => 'approved',
                        'reason' => 'إجازة مرضية'
                    ]
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getEmployeeLeaveBalance failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);
            
            return null;
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
            $employee = User::find($employeeId);
            
            if (!$employee) {
                return null;
            }
            
            // Check if user can access this employee
            if (!$this->permissionService->canAccessEmployee($user, $employee)) {
                return null;
            }
            
            $limit = $options['limit'] ?? 30;
            $fromDate = $options['from_date'] ?? now()->subDays($limit)->format('Y-m-d');
            $toDate = $options['to_date'] ?? now()->format('Y-m-d');
            
            // Mock attendance data - في التطبيق الحقيقي ستأتي من قاعدة البيانات
            $attendance = [];
            $presentDays = 0;
            $totalHours = 0;
            $lateDays = 0;
            $earlyLeaveDays = 0;
            
            $startDate = \Carbon\Carbon::parse($fromDate);
            $endDate = \Carbon\Carbon::parse($toDate);
            
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                // Skip weekends (Friday and Saturday in many Arab countries)
                if ($date->isFriday() || $date->isSaturday()) {
                    continue;
                }
                
                // 90% attendance rate
                if (rand(1, 10) > 1) {
                    $checkInHour = rand(7, 9);
                    $checkInMinute = rand(0, 59);
                    $checkOutHour = rand(16, 18);
                    $checkOutMinute = rand(0, 59);
                    
                    $checkIn = sprintf('%02d:%02d:00', $checkInHour, $checkInMinute);
                    $checkOut = sprintf('%02d:%02d:00', $checkOutHour, $checkOutMinute);
                    
                    $hoursWorked = ($checkOutHour - $checkInHour) + (($checkOutMinute - $checkInMinute) / 60);
                    $hoursWorked = round($hoursWorked, 1);
                    
                    $isLate = $checkInHour > 8 || ($checkInHour == 8 && $checkInMinute > 15);
                    $isEarlyLeave = $checkOutHour < 17 || ($checkOutHour == 17 && $checkOutMinute < 0);
                    
                    if ($isLate) $lateDays++;
                    if ($isEarlyLeave) $earlyLeaveDays++;
                    
                    $attendance[] = [
                        'date' => $date->format('Y-m-d'),
                        'day_name' => $date->locale('ar')->dayName,
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                        'hours_worked' => $hoursWorked,
                        'status' => 'present',
                        'is_late' => $isLate,
                        'is_early_leave' => $isEarlyLeave,
                        'overtime_hours' => max(0, $hoursWorked - 8),
                        'notes' => $isLate ? 'تأخير' : ($isEarlyLeave ? 'مغادرة مبكرة' : null)
                    ];
                    
                    $presentDays++;
                    $totalHours += $hoursWorked;
                } else {
                    $attendance[] = [
                        'date' => $date->format('Y-m-d'),
                        'day_name' => $date->locale('ar')->dayName,
                        'check_in' => null,
                        'check_out' => null,
                        'hours_worked' => 0,
                        'status' => rand(1, 3) == 1 ? 'sick_leave' : (rand(1, 2) == 1 ? 'annual_leave' : 'absent'),
                        'is_late' => false,
                        'is_early_leave' => false,
                        'overtime_hours' => 0,
                        'notes' => 'غياب'
                    ];
                }
            }
            
            $workingDays = count($attendance);
            $absentDays = $workingDays - $presentDays;
            $averageHours = $presentDays > 0 ? round($totalHours / $presentDays, 1) : 0;
            $attendanceRate = $workingDays > 0 ? round(($presentDays / $workingDays) * 100, 1) : 0;
            
            return [
                'employee' => [
                    'id' => $employee->user_id,
                    'name' => $employee->first_name . ' ' . $employee->last_name,
                    'employee_id' => $employee->user_details->employee_id ?? 'EMP' . str_pad($employee->user_id, 4, '0', STR_PAD_LEFT)
                ],
                'period' => [
                    'from' => $fromDate,
                    'to' => $toDate,
                    'total_days' => $workingDays
                ],
                'attendance' => array_reverse($attendance), // Most recent first
                'summary' => [
                    'total_working_days' => $workingDays,
                    'present_days' => $presentDays,
                    'absent_days' => $absentDays,
                    'late_days' => $lateDays,
                    'early_leave_days' => $earlyLeaveDays,
                    'total_hours_worked' => round($totalHours, 1),
                    'average_hours_per_day' => $averageHours,
                    'attendance_rate' => $attendanceRate,
                    'punctuality_rate' => $presentDays > 0 ? round((($presentDays - $lateDays) / $presentDays) * 100, 1) : 0
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getEmployeeAttendance failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'options' => $options,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Get employee salary details with permission check
     * 
     * @param User $user Current user requesting the data
     * @param int $employeeId Target employee ID
     * @param array $options Additional options (limit, year)
     * @return array|null
     */
    public function getEmployeeSalaryDetails(User $user, int $employeeId, array $options = []): ?array
    {
        try {
            $employee = User::with('user_details')->find($employeeId);
            
            if (!$employee) {
                return null;
            }
            
            // Check if user can access this employee
            if (!$this->permissionService->canAccessEmployee($user, $employee)) {
                return null;
            }
            
            // Check if user can view salaries
            if (!$this->permissionService->canViewSalaries($user)) {
                return null;
            }
            
            $limit = $options['limit'] ?? 12;
            $year = $options['year'] ?? now()->year;
            
            // Get basic salary from employee details
            $basicSalary = $employee->user_details->salary ?? 5000;
            
            // Mock salary components
            $salaryComponents = [
                'basic_salary' => $basicSalary,
                'housing_allowance' => round($basicSalary * 0.15, 2),
                'transport_allowance' => round($basicSalary * 0.05, 2),
                'food_allowance' => 500,
                'overtime_pay' => rand(0, 1000),
                'bonus' => rand(0, 2000),
                'commission' => rand(0, 1500)
            ];
            
            $totalAllowances = array_sum(array_slice($salaryComponents, 1));
            $grossSalary = $salaryComponents['basic_salary'] + $totalAllowances;
            
            // Mock deductions
            $deductions = [
                'social_insurance' => round($basicSalary * 0.09, 2),
                'income_tax' => round($grossSalary * 0.05, 2),
                'medical_insurance' => 200,
                'loan_deduction' => rand(0, 500),
                'advance_deduction' => rand(0, 300)
            ];
            
            $totalDeductions = array_sum($deductions);
            $netSalary = $grossSalary - $totalDeductions;
            
            // Mock salary history
            $salaryHistory = [];
            for ($i = 0; $i < $limit; $i++) {
                $month = now()->subMonths($i);
                $monthlyBasic = $basicSalary + rand(-200, 200); // Small variations
                $monthlyGross = $monthlyBasic + $totalAllowances + rand(-500, 500);
                $monthlyDeductions = $totalDeductions + rand(-100, 100);
                $monthlyNet = $monthlyGross - $monthlyDeductions;
                
                $salaryHistory[] = [
                    'month' => $month->format('Y-m'),
                    'month_name' => $month->locale('ar')->monthName . ' ' . $month->year,
                    'basic_salary' => round($monthlyBasic, 2),
                    'allowances' => round($totalAllowances + rand(-200, 200), 2),
                    'deductions' => round($monthlyDeductions, 2),
                    'gross_salary' => round($monthlyGross, 2),
                    'net_salary' => round($monthlyNet, 2),
                    'status' => $i === 0 ? 'pending' : 'paid',
                    'pay_date' => $i === 0 ? null : $month->endOfMonth()->format('Y-m-d')
                ];
            }
            
            return [
                'employee' => [
                    'id' => $employee->user_id,
                    'name' => $employee->first_name . ' ' . $employee->last_name,
                    'employee_id' => $employee->user_details->employee_id ?? 'EMP' . str_pad($employee->user_id, 4, '0', STR_PAD_LEFT),
                    'department' => $employee->user_details->department->department_name ?? null,
                    'designation' => $employee->user_details->designation->designation_name ?? null
                ],
                'current_salary' => [
                    'components' => $salaryComponents,
                    'deductions' => $deductions,
                    'gross_salary' => round($grossSalary, 2),
                    'total_deductions' => round($totalDeductions, 2),
                    'net_salary' => round($netSalary, 2)
                ],
                'salary_history' => array_reverse($salaryHistory), // Most recent first
                'summary' => [
                    'year' => $year,
                    'total_months' => count($salaryHistory),
                    'total_gross_paid' => round(collect($salaryHistory)->where('status', 'paid')->sum('gross_salary'), 2),
                    'total_net_paid' => round(collect($salaryHistory)->where('status', 'paid')->sum('net_salary'), 2),
                    'average_net_salary' => round(collect($salaryHistory)->where('status', 'paid')->avg('net_salary'), 2),
                    'highest_net_salary' => round(collect($salaryHistory)->where('status', 'paid')->max('net_salary'), 2),
                    'lowest_net_salary' => round(collect($salaryHistory)->where('status', 'paid')->min('net_salary'), 2)
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('EmployeeManagementService::getEmployeeSalaryDetails failed', [
                'user_id' => $user->user_id,
                'employee_id' => $employeeId,
                'options' => $options,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
}
