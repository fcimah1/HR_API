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
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use Illuminate\Support\Facades\Log;

class EmployeeRepository implements EmployeeRepositoryInterface
{
    public function __construct(
        private readonly User $model
    ) {}

    public function getPaginatedEmployees(EmployeeFilterDTO $filters): LengthAwarePaginator
    {
        $query = $this->buildBaseQuery($filters->company_id);

        // Load details relationship
        $query->with(['user_details.designation', 'user_details.department']);

        if ($filters->search !== null && trim($filters->search) !== '') {
            $searchTerm = '%' . $filters->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', $searchTerm)
                    ->orWhere('last_name', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm)
                    ->orWhere('username', 'like', $searchTerm);
            });
        }
        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        return $query->paginate($filters->limit, ['*'], 'page', $filters->page);
    }

    public function findEmployeeInCompany(int $employeeId, int $companyId): ?User
    {
        return $this->model->with(['user_details.designation', 'user_details.department'])
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

        return [
            'total_employees' => $totalEmployees,
            'active_employees' => $activeEmployees,
            'inactive_employees' => $inactiveEmployees,
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
            $query->where('user_type', $filters->user_type);
        }

        if ($filters->hasActiveFilter()) {
            $query->where('is_active', $filters->is_active ? 1 : 0);
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

        $sortBy = in_array($filters->sort_by, $allowedSortFields)
            ? $filters->sort_by
            : 'first_name';

        $query->orderBy($sortBy, $filters->sort_direction);
    }

    public function createEmployee(CreateEmployeeDTO $employeeData): User
    {
        return DB::transaction(function () use ($employeeData) {
            // Create user
            $user = $this->model->create($employeeData->getUserData());

            // Create user details if provided
            $detailsData = $employeeData->getUserDetailsData($user->user_id, $user->company_id);
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
                $updated = $this->model->where('user_id', $employeeData->user_id)
                    ->update($userData) > 0;
            }

            // Update user details if provided
            if ($employeeData->hasDetailsUpdates()) {
                $detailsData = $employeeData->getUserDetailsData();

                // Check if details exist
                $existingDetails = UserDetails::where('user_id', $employeeData->user_id)->first();
                $employee = $this->model->find($employeeData->user_id);

                if ($existingDetails) {
                    // Update existing details
                    $existingDetails->update($detailsData);
                } else {
                    // Create new details
                    $detailsData['user_id'] = $employeeData->user_id;
                    $detailsData['company_id'] = $employee->company_id;
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
     * @param int|null $excludeEmployeeId Optional employee ID to exclude (e.g. the target employee)
     * @return array
     */
    public function getDutyEmployee(int $id, ?string $search = null, ?int $employeeId = null, ?int $departmentId = null, ?int $excludeEmployeeId = null): array
    {
        $query = User::where('company_id', $id)
            ->where('is_active', 1)
            ->where('user_type', 'staff');

        // Filter by employee_id if provided
        if ($employeeId !== null) {
            $query->where('user_id', $employeeId);
        }

        // Exclude employee_id if provided
        if ($excludeEmployeeId !== null) {
            $query->where('user_id', '!=', $excludeEmployeeId);
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
                    'hierarchy_level' => $user->user_details->designation->hierarchy_level ?? null,
                    'department_id' => $user->user_details->department_id ?? null,
                    'department_name' => $user->user_details->department->department_name ?? 'N/A',
                    'designation_id' => $user->user_details->designation_id ?? null,
                    'designation_name' => $user->user_details->designation->designation_name ?? 'N/A',
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
    public function getEmployeesForNotify(int $companyId, int $currentUserId, ?int $currentHierarchyLevel = null, ?int $currentDepartmentId = null, ?string $search = null): array
    {

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
     * @return array|null
     */
    public function getUserWithHierarchyInfo(int $userId): ?array
    {
        $user = User::with('user_details.designation')
            ->where('user_id', $userId)
            ->first();

        return $user ? $user->toArray() : null;
    }

    public function getAttendanceRecords(int $employeeId, string $fromDate, string $toDate)
    {
        return Attendance::where('employee_id', $employeeId)
            ->whereBetween('attendance_date', [$fromDate, $toDate])
            ->get()
            ->keyBy('attendance_date');
    }

    public function getApprovedLeaves(int $employeeId, string $fromDate, string $toDate)
    {
        return LeaveApplication::forEmployee($employeeId)
            ->approved()
            ->where(function ($q) use ($fromDate, $toDate) {
                $q->whereBetween('from_date', [$fromDate, $toDate])
                    ->orWhereBetween('to_date', [$fromDate, $toDate])
                    ->orWhere(function ($q2) use ($fromDate, $toDate) {
                        $q2->where('from_date', '<=', $fromDate)
                            ->where('to_date', '>=', $toDate);
                    });
            })
            ->with('leaveType')
            ->get();
    }

    public function getHolidays(int $companyId, string $fromDate, string $toDate)
    {
        return Holiday::where('company_id', $companyId)
            ->published()
            ->betweenDates($fromDate, $toDate)
            ->get();
    }

    public function getLeaveTypes(int $companyId)
    {
        return DB::table('ci_erp_constants')
            ->where('type', 'leave_type')
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('constants_id');
    }

    public function getLeaveApplicationsByYear(int $employeeId, int $companyId, int $year, ?int $status = null)
    {
        $query = DB::table('ci_leave_applications')
            ->where('employee_id', $employeeId)
            ->where('company_id', $companyId)
            ->where('leave_year', $year);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function getLeaveAdjustmentsByYear(int $employeeId, int $companyId, int $year)
    {
        return DB::table('ci_leave_adjustment')
            ->where('employee_id', $employeeId)
            ->where('company_id', $companyId)
            ->where('status', 1) // Approved only
            ->whereYear('adjustment_date', $year)
            ->get();
    }

    public function getRecentLeaves(int $employeeId, int $companyId, int $limit = 5)
    {
        return DB::table('ci_leave_applications')
            ->leftJoin('ci_erp_constants', 'ci_leave_applications.leave_type_id', '=', 'ci_erp_constants.constants_id')
            ->where('ci_leave_applications.employee_id', $employeeId)
            ->where('ci_leave_applications.company_id', $companyId)
            ->where('ci_leave_applications.status', 1) // Approved only
            ->orderBy('ci_leave_applications.created_at', 'desc')
            ->limit($limit)
            ->select('ci_leave_applications.*', 'ci_erp_constants.category_name')
            ->get();
    }


    // generate random employee id from 6 numbers to be unique
    public function generateNextEmployeeIdnum(int $companyId): string
    {
        do {
            $randomId = (string) mt_rand(100000, 999999);
            $exists = DB::table('ci_erp_users_details')
                ->where('employee_id', $randomId)
                ->exists();
        } while ($exists);

        return $randomId;
    }

    public function getAdvancedStats(int $companyId, array $options = []): array
    {
        $userIds = $options['user_ids'] ?? null; // Filtered by permissions in service

        $stats = [];

        // 1. By department
        $deptQuery = DB::table('ci_erp_users')
            ->join('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
            ->join('ci_departments', 'ci_erp_users_details.department_id', '=', 'ci_departments.department_id')
            ->where('ci_erp_users.company_id', $companyId)
            ->where('ci_erp_users.user_type', 'staff');

        if ($userIds) $deptQuery->whereIn('ci_erp_users.user_id', $userIds);

        $stats['employees_by_department'] = $deptQuery
            ->select([
                'ci_departments.department_id',
                'ci_departments.department_name',
                DB::raw('COUNT(*) as total_employees'),
                DB::raw('SUM(CASE WHEN ci_erp_users.is_active = 1 THEN 1 ELSE 0 END) as active_employees'),
            ])
            ->groupBy('ci_departments.department_id', 'ci_departments.department_name')
            ->get()
            ->toArray();

        // 2. By designation
        $desigQuery = DB::table('ci_erp_users')
            ->join('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
            ->join('ci_designations', 'ci_erp_users_details.designation_id', '=', 'ci_designations.designation_id')
            ->where('ci_erp_users.company_id', $companyId)
            ->where('ci_erp_users.user_type', 'staff');

        if ($userIds) $desigQuery->whereIn('ci_erp_users.user_id', $userIds);

        $stats['employees_by_designation'] = $desigQuery
            ->select([
                'ci_designations.designation_id',
                'ci_designations.designation_name',
                'ci_designations.hierarchy_level',
                DB::raw('COUNT(*) as total_employees'),
                DB::raw('SUM(CASE WHEN ci_erp_users.is_active = 1 THEN 1 ELSE 0 END) as active_employees'),
            ])
            ->groupBy('ci_designations.designation_id', 'ci_designations.designation_name', 'ci_designations.hierarchy_level')
            ->get()
            ->toArray();

        // 3. By gender
        $genderQuery = DB::table('ci_erp_users')
            ->where('company_id', $companyId)
            ->where('user_type', 'staff')
            ->whereNotNull('gender');

        if ($userIds) $genderQuery->whereIn('user_id', $userIds);

        $stats['by_gender'] = $genderQuery
            ->select('gender', DB::raw('COUNT(*) as count'))
            ->groupBy('gender')
            ->get()
            ->toArray();

        // 4. By age group
        $ageQuery = DB::table('ci_erp_users')
            ->join('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
            ->where('ci_erp_users.company_id', $companyId)
            ->where('ci_erp_users.user_type', 'staff')
            ->whereNotNull('ci_erp_users_details.date_of_birth');

        if ($userIds) $ageQuery->whereIn('ci_erp_users.user_id', $userIds);

        $stats['by_age_group'] = $ageQuery
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
            ->get()
            ->toArray();

        // 5. Salary stats
        $salaryQuery = DB::table('ci_erp_users')
            ->join('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
            ->where('ci_erp_users.company_id', $companyId)
            ->where('ci_erp_users.user_type', 'staff')
            ->where('ci_erp_users_details.basic_salary', '>', 0);

        if ($userIds) $salaryQuery->whereIn('ci_erp_users.user_id', $userIds);

        $stats['salary_sums'] = $salaryQuery
            ->selectRaw('
                AVG(basic_salary) as average_salary,
                SUM(basic_salary) as total_salary_cost,
                MIN(basic_salary) as min_salary,
                MAX(basic_salary) as max_salary,
                COUNT(*) as employees_with_salary
            ')
            ->first();

        // 6. Recent hires
        $recentQuery = DB::table('ci_erp_users')
            ->join('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
            ->where('ci_erp_users.company_id', $companyId)
            ->where('ci_erp_users.user_type', 'staff')
            ->where('ci_erp_users_details.date_of_joining', '>=', now()->subDays(30));

        if ($userIds) $recentQuery->whereIn('ci_erp_users.user_id', $userIds);

        $stats['recent_hires_count'] = $recentQuery->count();

        return $stats;
    }

    /**
     * Update employee password
     */
    public function updateEmployeePassword(int $employeeId, int $companyId, string $hashedPassword): bool
    {
        try {
            $affected = DB::table('ci_erp_users')
                ->where('user_id', $employeeId)
                ->where('company_id', $companyId)
                ->update([
                    'password' => $hashedPassword,
                ]);

            return $affected > 0;
        } catch (\Exception $e) {
            Log::error('EmployeeRepository::updateEmployeePassword failed', [
                'employee_id' => $employeeId,
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل في تحديث كلمة المرور');
        }
    }

    /**
     * Update employee profile image
     */
    public function updateEmployeeProfileImage(int $employeeId, string $imageUrl): bool
    {
        try {
            $affected = DB::table('ci_erp_users')
                ->where('user_id', $employeeId)
                ->update([
                    'profile_photo' => $imageUrl
                ]);

            return $affected > 0;
        } catch (\Exception $e) {
            Log::error('EmployeeRepository::updateEmployeeProfileImage failed', [
                'employee_id' => $employeeId,
                'image_url' => $imageUrl,
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل في تحديث صورة الموظف');
        }
    }

    /**
     * Insert employee document
     */
    public function insertEmployeeDocument(array $documentData): int
    {
        try {
            return DB::table('ci_users_documents')->insertGetId([
                'user_id' => $documentData['user_id'],
                'company_id' => $documentData['company_id'],
                'document_name' => $documentData['document_name'],
                'document_type' => $documentData['document_type'],
                'document_file' => $documentData['file_path'],
                'expiry_date' => $documentData['expiration_date'] ?? null,
                'created_at' => now(),

            ]);
        } catch (\Exception $e) {
            Log::error('EmployeeRepository::insertEmployeeDocument failed', [
                'document_data' => $documentData,
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل في إضافة المستندات');
        }
    }

    /**
     * Update employee profile info (username, email)
     */
    public function updateEmployeeProfileInfo(int $employeeId, int $companyId, array $profileData): bool
    {
        try {
            $updateData = array_filter($profileData);
            if (empty($updateData)) {
                return true;
            }


            $affected = DB::table('ci_erp_users')
                ->where('user_id', $employeeId)
                ->where('company_id', $companyId)
                ->update($updateData);

            return $affected > 0;
        } catch (\Exception $e) {
            Log::error('EmployeeRepository::updateEmployeeProfileInfo failed', [
                'employee_id' => $employeeId,
                'company_id' => $companyId,
                'profile_data' => $profileData,
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل في إتعديل بيانات الموظف');
        }
    }

    /**
     * Update employee CV data
     */
    public function updateEmployeeCV(int $employeeId, array $cvData): bool
    {
        try {
            $updateData = array_filter($cvData, function ($value) {
                return $value !== null;
            });

            if (empty($updateData)) {
                return true;
            }


            $affected = DB::table('ci_erp_users_details')
                ->where('user_id', $employeeId)
                ->update($updateData);

            return $affected > 0;
        } catch (\Exception $e) {
            Log::error('EmployeeRepository::updateEmployeeCV failed', [
                'employee_id' => $employeeId,
                'cv_data' => $cvData,
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل في إتعديل السيرة الذاتية و الخبره');
        }
    }

    /**
     * Update employee social links
     */
    public function updateEmployeeSocialLinks(int $employeeId, array $socialData): bool
    {
        try {
            $updateData = array_filter($socialData, function ($value) {
                return $value !== null;
            });

            if (empty($updateData)) {
                return true;
            }

            $affected = DB::table('ci_erp_users_details')
                ->where('user_id', $employeeId)
                ->update($updateData);

            return $affected > 0;
        } catch (\Exception $e) {
            Log::error('EmployeeRepository::updateEmployeeSocialLinks failed', [
                'employee_id' => $employeeId,
                'social_data' => $socialData,
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل في إتعديل البيانات الاجتماعية');
        }
    }

    /**
     * Update or insert employee bank info
     */
    public function updateEmployeeBankInfo(int $employeeId, array $bankData): bool
    {
        try {
            $updateData = array_filter($bankData, function ($value) {
                return $value !== null;
            });

            if (empty($updateData)) {
                Log::error('EmployeeRepository::updateEmployeeBankInfo failed', [
                    'employee_id' => $employeeId,
                    'bank_data' => $bankData,
                    'error' => 'No data to update after filtering'
                ]);
                throw new \Exception(message: 'فشل في إتعديل البيانات البنكيه');
            }

            // Check if user_details record exists
            $existingRecord = DB::table('ci_erp_users_details')
                ->where('user_id', $employeeId)
                ->first();

            Log::info('EmployeeRepository::updateEmployeeBankInfo attempting update', [
                'employee_id' => $employeeId,
                'bank_data' => $bankData,
                'update_data' => $updateData,
                'existing_record_exists' => $existingRecord ? true : false
            ]);

            if (!$existingRecord) {
                Log::error('EmployeeRepository::updateEmployeeBankInfo failed', [
                    'employee_id' => $employeeId,
                    'bank_data' => $bankData,
                    'error' => 'No user_details record found for employee'
                ]);
                throw new \Exception(message: 'فشل في إتعديل البيانات البنكيه');
            }

            $affected = DB::table('ci_erp_users_details')
                ->where('user_id', $employeeId)
                ->update($updateData);

            Log::info('EmployeeRepository::updateEmployeeBankInfo update result', [
                'employee_id' => $employeeId,
                'affected_rows' => $affected,
                'success' => $affected > 0
            ]);

            // Return true if record exists (even if no rows were affected due to same values)
            // MySQL returns 0 affected rows when updating with identical values
            return true;
        } catch (\Exception $e) {
            Log::error('EmployeeRepository::updateEmployeeBankInfo failed', [
                'employee_id' => $employeeId,
                'bank_data' => $bankData,
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل في إتعديل البيانات البنكيه');
        }
    }

    public function addEmployeeFamilyData(int $employeeId, array $familyData): bool
    {
        try {
            // Get the employee's company_id
            $employee = DB::table('ci_erp_users')->where('user_id', $employeeId)->select('company_id')->first();
            $companyId = $employee ? $employee->company_id : 0;

            // Map request fields to database columns for insertion
            $insertData = [
                'user_id' => $employeeId,
                'company_id' => $companyId,
                'contact_full_name' => $familyData['relative_full_name'] ?? null,
                'contact_email' => $familyData['relative_email'] ?? null,
                'contact_phone_no' => $familyData['relative_phone'] ?? null,
                'place' => $familyData['relative_place'] ?? null,
                'contact_address' => $familyData['relative_address'] ?? null,
                'relation' => $familyData['relative_relation'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::table('ci_erp_employee_contacts')->insert($insertData);

            return true;
        } catch (\Exception $e) {
            Log::error('EmployeeRepository::addEmployeeFamilyData failed', [
                'employee_id' => $employeeId,
                'family_data' => $familyData,
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل في إضافة البيانات العائلية');
        }
    }

    public function deleteEmployeeFamilyData(int $contactId): bool
    {
        try {
            $affected = DB::table('ci_erp_employee_contacts')
                ->where('contact_id', $contactId)
                ->delete();

            return $affected > 0;
        } catch (\Exception $e) {
            Log::error('EmployeeRepository::deleteEmployeeFamilyData failed', [
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception(message: 'فشل في حذف البيانات العائلية');
        }
    }
}
