<?php

namespace App\Repository;

use App\DTOs\Employee\EmployeeFilterDTO;
use App\DTOs\Employee\CreateEmployeeDTO;
use App\DTOs\Employee\UpdateEmployeeDTO;
use App\Models\User;
use App\Models\UserDetails;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use App\Repository\Interface\EmployeeRepositoryInterface;

class EmployeeRepository implements EmployeeRepositoryInterface
{
    public function __construct(
        private readonly User $model
    ) {}

    public function getPaginatedEmployees(EmployeeFilterDTO $filters): LengthAwarePaginator
    {
        $query = $this->buildBaseQuery($filters->companyId);

        // Load details relationship
        $query->with('user_details');

        if ($filters->search !== null && trim($filters->search) !== '') {
            $searchTerm = '%' . $filters->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                // البحث في بيانات الموظف
                $q->whereHas('user_details', function ($subQuery) use ($searchTerm) {
                    $subQuery->where('first_name', 'like', $searchTerm)
                        ->orWhere('last_name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm);
                });
            });
        }
        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        return $query->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }

    public function findEmployeeInCompany(int $employeeId, int $companyId): ?User
    {
        return $this->model->with('user_details')
            ->where('user_id', $employeeId)
            ->where('company_id', $companyId)
            ->first();
    }

    public function getEmployeeStats(int $companyId): array
    {
        $totalEmployees = $this->model->where('company_id', $companyId)->count();
        $activeEmployees = $this->model->where('company_id', $companyId)
            ->where('is_active', 1)
            ->count();
        $inactiveEmployees = $totalEmployees - $activeEmployees;

        $byUserType = $this->model->where('company_id', $companyId)
            ->selectRaw('user_type, COUNT(*) as count')
            ->groupBy('user_type')
            ->pluck('count', 'user_type')
            ->toArray();

        $loggedInEmployees = $this->model->where('company_id', $companyId)
            ->where('is_logged_in', 1)
            ->count();

        return [
            'total_employees' => $totalEmployees,
            'active_employees' => $activeEmployees,
            'inactive_employees' => $inactiveEmployees,
            'logged_in_employees' => $loggedInEmployees,
            'by_user_type' => $byUserType,
        ];
    }

    public function getAllEmployeesInCompany(int $companyId): Collection
    {
        return $this->model
            ->where('company_id', $companyId)
            ->where('is_active', 1)
            ->orderBy('first_name')
            ->get();
    }

    public function employeeExistsInCompany(int $employeeId, int $companyId): bool
    {
        return $this->model
            ->where('user_id', $employeeId)
            ->where('company_id', $companyId)
            ->exists();
    }

    public function getEmployeesByType(int $companyId, string $userType): Collection
    {
        return $this->model
            ->where('company_id', $companyId)
            ->where('user_type', $userType)
            ->where('is_active', 1)
            ->orderBy('first_name')
            ->get();
    }

    public function getActiveEmployeesCount(int $companyId): int
    {
        return $this->model
            ->where('company_id', $companyId)
            ->where('is_active', 1)
            ->count();
    }

    public function searchEmployees(int $companyId, string $searchTerm): Collection
    {
        return $this->model
            ->where('company_id', $companyId)
            ->where('is_active', 1)
            ->where(function (Builder $query) use ($searchTerm) {
                $query->where('first_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('email', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('username', 'LIKE', "%{$searchTerm}%");
            })
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Build base query for employees
     */
    private function buildBaseQuery(?int $companyId = null): Builder
    {
        $query = $this->model->newQuery();

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    /**
     * Apply filters to the query
     */
    private function applyFilters(Builder $query, EmployeeFilterDTO $filters): void
    {
        if ($filters->hasSearchFilter()) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('first_name', 'LIKE', "%{$filters->search}%")
                    ->orWhere('last_name', 'LIKE', "%{$filters->search}%")
                    ->orWhere('email', 'LIKE', "%{$filters->search}%")
                    ->orWhere('username', 'LIKE', "%{$filters->search}%");
            });
        }

        if ($filters->hasUserTypeFilter()) {
            $query->where('user_type', $filters->userType);
        }

        if ($filters->hasActiveFilter()) {
            $query->where('is_active', $filters->isActive ? 1 : 0);
        }
    }

    /**
     * Apply sorting to the query
     */
    private function applySorting(Builder $query, EmployeeFilterDTO $filters): void
    {
        $allowedSortFields = [
            'first_name',
            'last_name',
            'email',
            'username',
            'user_type',
            'created_at',
            'last_login_date'
        ];

        $sortBy = in_array($filters->sortBy, $allowedSortFields)
            ? $filters->sortBy
            : 'first_name';

        $query->orderBy($sortBy, $filters->sortDirection);
    }

    public function createEmployee(CreateEmployeeDTO $employeeData): User
    {
        return DB::transaction(function () use ($employeeData) {
            // Create user
            $user = $this->model->create($employeeData->getUserData());

            // Create user details if provided
            $detailsData = $employeeData->getUserDetailsData($user->user_id);
            if (!empty($detailsData)) {
                UserDetails::create($detailsData);
            }

            // Load details relationship
            $user->load('user_details');

            return $user;
        });
    }

    public function updateEmployee(UpdateEmployeeDTO $employeeData): bool
    {
        return DB::transaction(function () use ($employeeData) {
            $updated = false;

            // Update user data if provided
            if ($employeeData->hasUserUpdates()) {
                $userData = $employeeData->getUserData();
                $updated = $this->model->where('user_id', $employeeData->userId)
                    ->update($userData) > 0;
            }

            // Update user details if provided
            if ($employeeData->hasDetailsUpdates()) {
                $detailsData = $employeeData->getUserDetailsData();

                // Check if details exist
                $existingDetails = UserDetails::where('user_id', $employeeData->userId)->first();

                if ($existingDetails) {
                    // Update existing details
                    $existingDetails->update($detailsData);
                } else {
                    // Create new details
                    $detailsData['user_id'] = $employeeData->userId;
                    UserDetails::create($detailsData);
                }

                $updated = true;
            }

            return $updated;
        });
    }

    public function deleteEmployee(int $employeeId, int $companyId): bool
    {
        return DB::transaction(function () use ($employeeId, $companyId) {
            // Delete user details first (due to foreign key)
            UserDetails::where('user_id', $employeeId)->delete();

            // Delete user
            return $this->model->where('user_id', $employeeId)
                ->where('company_id', $companyId)
                ->delete() > 0;
        });
    }

    public function getEmployeeWithDetails(int $employeeId, int $companyId): ?User
    {
        return $this->model->with('user_details')
            ->where('user_id', $employeeId)
            ->where('company_id', $companyId)
            ->first();
    }


    /**
     * Get active duty employees with optional search
     *
     * @param int $id Company ID
     * @param string|null $search Optional search term to filter users by name, email, or company name
     * @param int|null $employeeId Optional employee ID to filter by specific employee
     * @param int|null $departmentId Optional department ID to filter by same department
     * @return array
     */
    public function getDutyEmployee(int $id, ?string $search = null, ?int $employeeId = null, ?int $departmentId = null): array
    {
        $query = User::where('company_id', $id)
            ->where('is_active', 1);

        // Filter by employee_id if provided
        if ($employeeId !== null) {
            $query->where('user_id', $employeeId);
        }

        // Filter by department_id if provided
        if ($departmentId !== null) {
            $query->whereHas('user_details', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        // Add search condition if search term is provided
        if ($search) {
            $searchTerm = "%{$search}%";
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'LIKE', $searchTerm)
                    ->orWhere('last_name', 'LIKE', $searchTerm)
                    ->orWhere('email', 'LIKE', $searchTerm)
                    ->orWhere('company_name', 'LIKE', $searchTerm)
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$searchTerm]);
            });
        }

        return $query->select([
            'company_id',
            'user_id',
            'email',
            'first_name',
            'last_name',
            'company_name',
            DB::raw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name")
        ])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(function ($user) {
                return [
                    'user_id' => $user->user_id,
                    'company_id' => $user->company_id,
                    'full_name' => trim($user->first_name . ' ' . $user->last_name),
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'company_name' => $user->company_name,
                ];
            })
            ->toArray();
    }

    /**
     * Get employees who can receive notifications based on CanNotifyUser rules
     * Returns: company users, hierarchy level 1 users, or higher hierarchy managers in same department
     *
     * @param int $companyId Company ID
     * @param int $currentUserId Current user ID to exclude
     * @param int|null $currentHierarchyLevel Current user's hierarchy level
     * @param int|null $currentDepartmentId Current user's department ID
     * @param string|null $search Optional search term
     * @return \Illuminate\Support\Collection
     */
    public function getEmployeesForNotify(int $companyId, int $currentUserId, ?int $currentHierarchyLevel = null, ?int $currentDepartmentId = null, ?string $search = null): array {
        
        $query = User::with('user_details.designation')
            ->where('company_id', $companyId)
            ->where('is_active', 1)
            ->where('user_id', '!=', $currentUserId);

        // Add search condition if provided
        if ($search) {
            $searchTerm = "%{$search}%";
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'LIKE', $searchTerm)
                    ->orWhere('last_name', 'LIKE', $searchTerm)
                    ->orWhere('email', 'LIKE', $searchTerm);
            });
        }

        return $query->get()->toArray();
    }

    /**
     * Get user with hierarchy information
     *
     * @param int $userId
     * @return User|null
     */
    public function getUserWithHierarchyInfo(int $userId): ?array
    {
        $user = User::with('user_details.designation')
            ->where('user_id', $userId)
            ->first();

        return $user ? $user->toArray() : null;
    }
}
