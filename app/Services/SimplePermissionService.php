<?php

namespace App\Services;

use App\Models\User;
use App\Models\StaffRole;
use App\Models\OperationRestriction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SimplePermissionService
{
    // Cache TTL for permissions (5 minutes)
    private const PERMISSION_CACHE_TTL = 300;

    /**
     * التحقق من صلاحية المستخدم
     */
    public function checkPermission(User $user, string $permission): bool
    {
        $userType = strtolower(trim($user->user_type ?? ''));
        // super_user ومستخدم الشركة لهم صلاحيات كاملة ولا يعتمدون على جدول الأدوار
        if ($userType === 'super_user' || $userType === 'company') {
            return true;
        }

        // إذا لم يكن مرتبطًا بدور صالح، فلا صلاحيات
        if ($user->user_role_id <= 0) {
            return false;
        }

        // استخدام الـ Cache للصلاحيات
        $permissions = $this->getUserPermissions($user);
        return in_array($permission, $permissions);
    }

    /**
     * التحقق من أن المستخدم ينتمي لنفس الشركة
     */
    public function checkCompanyAccess(User $user, int $targetCompanyId): bool
    {
        // إذا كان صاحب الشركة (company_id = 0) يمكنه الوصول لشركته فقط
        if ($user->company_id == 0) {
            return $targetCompanyId == $user->user_id;
        }

        // إذا كان موظف، يمكنه الوصول لشركته فقط
        return $user->company_id == $targetCompanyId;
    }

    /**
     * التحقق من أن المستخدم يمكنه الوصول لبيانات موظف آخر
     */
    public function canAccessEmployee(User $user, User $targetEmployee): bool
    {
        // التحقق من نفس الشركة أولاً
        if (!$this->checkCompanyAccess($user, $targetEmployee->company_id)) {
            return false;
        }

        // إذا كان صاحب الشركة، يمكنه الوصول لجميع موظفيه
        $userType = strtolower(trim($user->user_type ?? ''));
        if ($userType === 'company' || $user->user_role_id == 0) {
            return true;
        }

        // إذا كان موظف، يمكنه الوصول لبياناته الشخصية فقط (إلا إذا كان له صلاحية)
        if ($user->user_id == $targetEmployee->user_id) {
            return true;
        }

        // التحقق من صلاحية عرض الموظفين
        return $this->checkPermission($user, 'employee.view.all');
    }

    /**
     * الحصول على جميع صلاحيات المستخدم (مع Cache)
     */
    public function getUserPermissions(User $user): array
    {
        $userType = strtolower(trim($user->user_type ?? ''));

        // مستخدم الشركة له جميع الصلاحيات
        if ($userType === 'company') {
            return ['*'];
        }

        if ($user->user_role_id <= 0) {
            return [];
        }

        // استخدام الـ Cache
        $cacheKey = "user_permissions.{$user->user_id}";

        // Try to get data from cache
        $cachedData = Cache::get($cacheKey);

        // Check if cache is valid and matches current role
        // This ensures that if the user changes role, we don't return stale permissions
        if ($cachedData && is_array($cachedData) && isset($cachedData['role_id']) && $cachedData['role_id'] == $user->user_role_id) {
            return $cachedData['permissions'] ?? [];
        }

        // Cache miss or stale (role mismatch) -> Fetch using User model's method for consistency
        // This ensures we use the same logic as login response
        $permissions = $user->getUserPermissions();

        // Store result in cache with role_id for future validation
        Cache::put($cacheKey, [
            'role_id' => $user->user_role_id,
            'permissions' => $permissions
        ], self::PERMISSION_CACHE_TTL);

        return $permissions;
    }


    /**
     * مسح cache صلاحيات المستخدم
     */
    public function clearUserPermissionsCache(int $userId): void
    {
        Cache::forget("user_permissions.{$userId}");
    }

    /**
     * التحقق من نوع المستخدم
     */
    public function isCompanyOwner(User $user): bool
    {
        return strtolower(trim($user->user_type ?? '')) === 'company' || ($user->company_id == 0 && $user->user_role_id == 0);
    }

    public function isEmployee(User $user): bool
    {
        return $user->company_id > 0 && $user->user_role_id > 0;
    }

    /**
     * التحقق إذا كان المستخدم Super User (الدعم الفني)
     */
    public function isSuperUser(User $user): bool
    {
        return strtolower(trim($user->user_type ?? '')) === 'super_user';
    }

    /**
     * Check if requester can override restrictions for target user
     * (Company Owner or Superior)
     */
    public function canOverrideRestriction(User $requester, User $target, ?string $restrictionPrefix = null, ?int $restrictionValue = null): bool
    {
        // 1. Company Owner Override (Always can override)
        if ($this->isCompanyOwner($requester)) {
            return true;
        }

        // If checking specific restriction, verify requester doesn't have the same restriction
        if ($restrictionPrefix && $restrictionValue !== null) {
            $requesterRestrictions = $this->getRestrictedValues($requester->user_id, $requester->company_id, $restrictionPrefix);
            if (in_array($restrictionValue, $requesterRestrictions)) {
                return false; // Requester is also restricted, so cannot override
            }
        }

        // Self-request cannot override own restrictions (unless company owner)
        if ($requester->user_id === $target->user_id) {
            return false;
        }

        // 2. Hierarchy Override (Superior requests for Subordinate)
        // Must be same company
        if ($requester->company_id !== $target->company_id) {
            return false;
        }

        // Ensure relationships are loaded
        $requester->loadMissing('user_details.designation');
        $target->loadMissing('user_details.designation');

        $requesterLevel = $requester->user_details?->designation?->hierarchy_level ?? null;
        $targetLevel = $target->user_details?->designation?->hierarchy_level ?? null;

        if (!is_null($requesterLevel) && !is_null($targetLevel)) {
            // Requester must be higher rank (numerically lower)
            if ($requesterLevel < $targetLevel) {
                return true;
            }
        }

        return false;
    }

    /**
     * الحصول على معرف الشركة الفعلي للمستخدم
     */
    public function getEffectiveCompanyId(User $user): int
    {
        // إذا كان صاحب الشركة، معرف الشركة هو user_id الخاص به
        if ($this->isCompanyOwner($user)) {
            return $user->user_id;
        }

        // إذا كان موظف، معرف الشركة هو company_id
        return $user->company_id;
    }

    /**
     * فلترة البيانات حسب الشركة
     */
    public function filterByCompany($query, User $user, string $companyColumn = 'company_id')
    {
        $effectiveCompanyId = $this->getEffectiveCompanyId($user);
        return $query->where($companyColumn, $effectiveCompanyId);
    }

    /**
     * الحصول على المستوى الهرمي للمستخدم
     */
    public function getUserHierarchyLevel(User $user): ?int
    {
        return $user->getHierarchyLevel();
    }

    /**
     * الحصول على معرف القسم للمستخدم
     */
    public function getUserDepartmentId(User $user): ?int
    {
        return $user->user_details?->department_id;
    }

    /**
     * التحقق إذا كان المستخدم يمكنه رؤية طلبات موظف آخر
     * بناءً على المستوى الهرمي والقسم
     */

    public function canViewEmployeeRequests(User $manager, User $employee): bool
    {
        // مدير الشركة يرى الجميع
        if ($this->isCompanyOwner($manager)) {
            // Check if the employee belongs to the company owner's company (which is the owner's user_id)
            return $employee->company_id == $manager->user_id;
        }

        // يجب أن يكون من نفس الشركة
        if ($manager->company_id !== $employee->company_id) {
            return false;
        }

        // التحقق من المستوى الهرمي
        $managerLevel = $this->getUserHierarchyLevel($manager);
        $employeeLevel = $this->getUserHierarchyLevel($employee);

        if ($managerLevel === null || $employeeLevel === null) {
            return false;
        }

        // يجب أن يكون المدير في مستوى أعلى (رقم أقل)
        // Hierarchy Logic: Manager (Level 1) can view Employee (Level 5).
        // Condition: Employee Level >= Manager Level for visibility.
        // Wait, if Manager is 1 (High) and Employee is 5 (Low).
        // If Manager wants to view Employee (5), Manager(1) < Employee(5).
        // So checking if Manager Level is GREATER than Employee Level means Manager is Junior to Employee.
        // If Manager(5) tries to view Manager(1). 5 > 1. Blocked.
        if ($managerLevel > $employeeLevel) {
            return false;
        }

        // التحقق من القيود (Restrictions)
        // بدلاً من فحص القسم الصارم، نفحص إذا كان قسم الموظف محظوراً على المدير
        $restriction = OperationRestriction::where('company_id', $manager->company_id)
            ->where('user_id', $manager->user_id)
            ->first();

        if ($restriction && !empty($restriction->restricted_operations)) {
            $operations = $restriction->restricted_operations;
            if (is_string($operations)) {
                $operations = explode(',', $operations);
            }

            $employeeDepartmentId = $this->getUserDepartmentId($employee);
            $employeeBranchId = $employee->user_details?->branch_id ?? 0;

            foreach ($operations as $op) {
                $op = trim($op);
                if ($employeeDepartmentId && str_starts_with($op, 'dept_')) {
                    $restrictedDeptId = (int)str_replace('dept_', '', $op);
                    if ($employeeDepartmentId == $restrictedDeptId) {
                        return false; // Restricted Department
                    }
                }
                if ($employeeBranchId && str_starts_with($op, 'branch_')) {
                    $restrictedBranchId = (int)str_replace('branch_', '', $op);
                    if ($employeeBranchId == $restrictedBranchId) {
                        return false; // Restricted Branch
                    }
                }
            }
        }

        return true;
    }

    /**
     * التحقق إذا كان المستخدم يمكنه الموافقة/الرفض على طلبات موظف آخر
     * يتطلب مستوى هرمي أعلى (أقل رقمياً) - لا يسمح بنفس المستوى أو النفس
     * 
     * @param User $approver الموظف الذي يقوم بالموافقة/الرفض
     * @param User $employee الموظف صاحب الطلب
     * @return bool
     */
    public function canApproveEmployeeRequests(User $approver, User $employee): bool
    {
        // لا يمكن للموظف الموافقة/الرفض على طلبه بنفسه
        if ($approver->user_id === $employee->user_id) {
            return false;
        }

        // مدير الشركة يمكنه الموافقة على الجميع
        if ($this->isCompanyOwner($approver)) {
            return $employee->company_id == $approver->user_id;
        }

        // يجب أن يكون من نفس الشركة
        if ($approver->company_id !== $employee->company_id) {
            return false;
        }

        // التحقق من المستوى الهرمي
        $approverLevel = $this->getUserHierarchyLevel($approver);
        $employeeLevel = $this->getUserHierarchyLevel($employee);

        if ($approverLevel === null || $employeeLevel === null) {
            return false;
        }

        // يجب أن يكون الموافق في مستوى أعلى صارم (رقم أقل) - لا يسمح بنفس المستوى
        // Approver Level (1) < Employee Level (5) = Can Approve
        // Approver Level (3) = Employee Level (3) = Cannot Approve (Same Level)
        // Approver Level (5) > Employee Level (3) = Cannot Approve (Lower Level)
        if ($approverLevel >= $employeeLevel) {
            return false;
        }

        // التحقق من القيود (Restrictions) - Department/Branch
        $restriction = OperationRestriction::where('company_id', $approver->company_id)
            ->where('user_id', $approver->user_id)
            ->first();

        if ($restriction && !empty($restriction->restricted_operations)) {
            $operations = $restriction->restricted_operations;
            if (is_string($operations)) {
                $operations = explode(',', $operations);
            }

            $employeeDepartmentId = $this->getUserDepartmentId($employee);
            $employeeBranchId = $employee->user_details?->branch_id ?? 0;

            foreach ($operations as $op) {
                $op = trim($op);
                if ($employeeDepartmentId && str_starts_with($op, 'dept_')) {
                    $restrictedDeptId = (int)str_replace('dept_', '', $op);
                    if ($employeeDepartmentId == $restrictedDeptId) {
                        return false; // Restricted Department
                    }
                }
                if ($employeeBranchId && str_starts_with($op, 'branch_')) {
                    $restrictedBranchId = (int)str_replace('branch_', '', $op);
                    if ($employeeBranchId == $restrictedBranchId) {
                        return false; // Restricted Branch
                    }
                }
            }
        }

        return true;
    }

    /**
     * فلترة الموظفين حسب المستوى الهرمي والقسم
     */
    /**
     * فلترة الموظفين حسب المستوى الهرمي والقيود
     */
    public function filterSubordinates($query, User $manager)
    {
        if ($this->isCompanyOwner($manager)) {
            // مدير الشركة يرى جميع موظفي الشركة
            return $query->where('company_id', $manager->user_id);
        }

        $managerLevel = $this->getUserHierarchyLevel($manager);

        if ($managerLevel === null) {
            return $query->whereRaw('1 = 0'); // لا يعرض أي شيء
        }

        // 1. الشركة
        $query->where('company_id', $manager->company_id);

        // 2. الهرمية: الموظف يجب أن يكون في مستوى أدنى (رقم أعلى) أو مساوي
        $query->whereHas('user_details.designation', function ($q) use ($managerLevel) {
            $q->where('hierarchy_level', '>=', $managerLevel);
        });

        // 3. القيود (Restrictions)
        $restrictedDepartments = [];
        $restrictedBranches = [];

        $restriction = OperationRestriction::where('company_id', $manager->company_id)
            ->where('user_id', $manager->user_id)
            ->first();

        if ($restriction && !empty($restriction->restricted_operations)) {
            $operations = $restriction->restricted_operations;
            if (is_string($operations)) {
                $operations = explode(',', $operations);
            }
            foreach ($operations as $op) {
                $op = trim($op);
                if (str_starts_with($op, 'dept_')) {
                    $restrictedDepartments[] = (int)str_replace('dept_', '', $op);
                }
                if (str_starts_with($op, 'branch_')) {
                    $restrictedBranches[] = (int)str_replace('branch_', '', $op);
                }
            }
        }

        if (!empty($restrictedDepartments)) {
            $query->whereHas('user_details', function ($q) use ($restrictedDepartments) {
                $q->whereNotIn('department_id', $restrictedDepartments);
            });
        }

        if (!empty($restrictedBranches)) {
            // Assuming branch_id is in user_details or we join it. 
            // Note: Branch relationship might need check if it exists in user_details table directly.
            // If not, we might need to join users table (if branch_id is there) or check mapping.
            // Based on User model, branch_id is on User possibly? Or UserDetails?
            // Let's assume UserDetails for consistency with Department, but check User model if needed.
            // User.php has `public function branches()`? No `public function user_details()`.
            // `OperationRestriction` logic assumes standard field.
            // Checking `ci_erp_users_details` usually has `branch_id`.
            $query->whereHas('user_details', function ($q) use ($restrictedBranches) {
                $q->whereNotIn('branch_id', $restrictedBranches);
            });
        }

        return $query;
    }

    /**
     * جلب الموظفين بناءً على الصلاحيات الهرمية
     *
     * @param int $userId - معرف المستخدم الحالي (مقدم الطلب)
     * @param int $companyId - معرف الشركة
     * @param bool $includeSelf - هل نضمن المستخدم نفسه في النتائج؟
     * @return array - قائمة الموظفين المسموح برؤيتهم
     */
    public function getEmployeesByHierarchy(int $userId, int $companyId, bool $includeSelf = true): array
    {
        $user = User::find($userId);
        if (!$user) {
            return [];
        }

        // 1. إذا كان صاحب الشركة (Admin)، يرى الجميع فوراً
        if ($this->isCompanyOwner($user)) {
            // For company owner, their user_id is the company_id for staff
            return DB::table('ci_erp_users')
                ->select(
                    'ci_erp_users.user_id',
                    'ci_erp_users.first_name',
                    'ci_erp_users.last_name',
                    'ci_erp_users_details.designation_id',
                    'ci_designations.designation_name as designation_name',
                    'ci_designations.hierarchy_level as hierarchy_level',
                    'ci_erp_users_details.department_id',
                    'ci_departments.department_name as department_name',

                )
                ->leftJoin('ci_erp_users_details', 'ci_erp_users_details.user_id', '=', 'ci_erp_users.user_id')
                ->leftJoin('ci_designations', 'ci_designations.designation_id', '=', 'ci_erp_users_details.designation_id')
                ->leftJoin('ci_departments', 'ci_departments.department_id', '=', 'ci_erp_users_details.department_id')
                ->where('ci_erp_users.company_id', $user->user_id)
                ->where('ci_erp_users.user_type', 'staff')
                ->where('ci_erp_users.is_active', 1)
                ->get()
                ->toArray();
        }

        // 2. تحليل القيود (Restrictions) - الأقسام والفروع المحظورة
        $restrictedDepartments = [];
        $restrictedBranches = [];

        $restriction = OperationRestriction::where('company_id', $companyId)
            ->where('user_id', $userId)
            ->first();

        if ($restriction && !empty($restriction->restricted_operations)) {
            $operations = $restriction->restricted_operations;
            if (is_string($operations)) {
                $operations = explode(',', $operations);
            }

            foreach ($operations as $op) {
                $op = trim($op);
                if (str_starts_with($op, 'dept_')) {
                    $restrictedDepartments[] = (int)str_replace('dept_', '', $op);
                }
                if (str_starts_with($op, 'branch_')) {
                    $restrictedBranches[] = (int)str_replace('branch_', '', $op);
                }
            }
        }

        // 3. معرفة المستوى الهرمي للمستخدم الحالي
        $currentHierarchyLevel = $this->getUserHierarchyLevel($user) ?? 5; // Default to 5 (lowest)

        // المستوى 0 يرى الجميع
        if ($currentHierarchyLevel === 0) {
            return DB::table('ci_erp_users')
                ->select(
                    'ci_erp_users.user_id',
                    'ci_erp_users.first_name',
                    'ci_erp_users.last_name',
                    'ci_erp_users_details.designation_id',
                    'ci_designations.designation_name as designation_name',
                    'ci_designations.hierarchy_level as hierarchy_level',
                    'ci_erp_users_details.department_id',
                    'ci_departments.department_name as department_name'
                )
                ->leftJoin('ci_erp_users_details', 'ci_erp_users_details.user_id', '=', 'ci_erp_users.user_id')
                ->leftJoin('ci_designations', 'ci_designations.designation_id', '=', 'ci_erp_users_details.designation_id')
                ->leftJoin('ci_departments', 'ci_departments.department_id', '=', 'ci_erp_users_details.department_id')
                ->where('ci_erp_users.company_id', $companyId)
                ->where('ci_erp_users.user_type', 'staff')
                ->where('ci_erp_users.is_active', 1)
                ->get()
                ->toArray();
        }

        // 4. بناء الاستعلام مع الشروط
        $baseQuery = DB::table('ci_erp_users')
            ->select(
                'ci_erp_users.user_id',
                'ci_erp_users.first_name',
                'ci_erp_users.last_name',
                'ci_erp_users_details.designation_id',
                'ci_designations.designation_name as designation_name',
                'ci_designations.hierarchy_level as hierarchy_level',
                'ci_erp_users_details.department_id',
                'ci_departments.department_name as department_name'
            )
            ->leftJoin('ci_erp_users_details', 'ci_erp_users_details.user_id', '=', 'ci_erp_users.user_id')
            ->leftJoin('ci_designations', 'ci_designations.designation_id', '=', 'ci_erp_users_details.designation_id')
            ->leftJoin('ci_departments', 'ci_departments.department_id', '=', 'ci_erp_users_details.department_id')
            ->where('ci_erp_users.company_id', $companyId)
            ->where('ci_erp_users.user_type', 'staff')
            ->where('ci_erp_users.is_active', 1);

        $baseQuery->where(function ($masterQ) use ($userId, $includeSelf, $currentHierarchyLevel, $restrictedDepartments, $restrictedBranches) {

            // Group 1: Matches Hierarchy & Restrictions & Staff Type
            $masterQ->where(function ($q) use ($currentHierarchyLevel, $restrictedDepartments, $restrictedBranches) {
                $q->where('ci_erp_users.user_type', 'staff');

                // Hierarchy Level Check (Level 0 sees all, otherwise check level)
                if ($currentHierarchyLevel > 0) {
                    $q->where('ci_designations.hierarchy_level', '>=', $currentHierarchyLevel);
                }

                // Restrictions
                if (!empty($restrictedDepartments)) {
                    $q->whereNotIn('ci_erp_users_details.department_id', $restrictedDepartments);
                }
                if (!empty($restrictedBranches)) {
                    $q->whereNotIn('ci_erp_users_details.branch_id', $restrictedBranches);
                }
            });

            // Group 2: Include Self (Explicitly allowed regardless of above)
            if ($includeSelf) {
                $masterQ->orWhere('ci_erp_users.user_id', $userId);
            }
        });

        if (!$includeSelf) {
            $baseQuery->where('ci_erp_users.user_id', '!=', $userId);
        }

        return $baseQuery->get()->toArray();
    }

    /**
     * Get restricted values for a specific prefix (e.g., 'travel_', 'designation_')
     * Returns an array of IDs extracted from the restricted operations list.
     * 
     * @param int $userId
     * @param int $companyId
     * @param string $prefix
     * @return array
     */
    public function getRestrictedValues(int $userId, int $companyId, string $prefix): array
    {
        $restrictedValues = [];

        $restriction = OperationRestriction::where('company_id', $companyId)
            ->where('user_id', $userId)
            ->first();

        if ($restriction && !empty($restriction->restricted_operations)) {
            $operations = $restriction->restricted_operations;
            if (is_string($operations)) {
                $operations = explode(',', $operations);
            }

            foreach ($operations as $op) {
                $op = trim($op);
                if (str_starts_with($op, $prefix)) {
                    // Extract ID by removing prefix. Handling potential malformed strings or empty values.
                    $val = str_replace($prefix, '', $op);
                    if (is_numeric($val)) {
                        $restrictedValues[] = (int)$val;
                    }
                }
            }
        }

        return $restrictedValues;
    }

    /**
     * Check if a specific operation string exists in user restrictions
     * (e.g., 'view_salary', 'report_attendance')
     * 
     * @param int $userId
     * @param int $companyId
     * @param string $operationKey
     * @return bool
     */
    public function hasRestriction(int $userId, int $companyId, string $operationKey): bool
    {
        $restriction = OperationRestriction::where('company_id', $companyId)
            ->where('user_id', $userId)
            ->first();

        if ($restriction && !empty($restriction->restricted_operations)) {
            $operations = is_string($restriction->restricted_operations)
                ? explode(',', $restriction->restricted_operations)
                : $restriction->restricted_operations;

            $operations = array_map('trim', $operations);

            return in_array($operationKey, $operations);
        }

        return false;
    }

    /**
     * Get all restrictions for a user categorized by type.
     * Use this for efficient bulk checking or passing restrictions to frontend.
     *
     * @param int $userId
     * @param int $companyId
     * @return array
     */
    public function getAllUserRestrictions(int $userId, int $companyId): array
    {
        $result = [
            'leave_types' => [],
            'travel_types' => [],
            'award_types' => [],
            'incident_types' => [],
            'goal_types' => [],
            'assets_categories' => [],
            'product_categories' => [],
            'competencies' => [],
            'competencies2' => [],
            'departments' => [],
            'branches' => [],
            'employee_sections' => [], // employee_contract, employee_salary, etc.
            'reports' => [], // report_projects, report_salary, etc.
            'raw_flags' => [] // Any other unclassified flags
        ];

        $restriction = OperationRestriction::where('company_id', $companyId)
            ->where('user_id', $userId)
            ->first();

        if ($restriction && !empty($restriction->restricted_operations)) {
            $operations = is_string($restriction->restricted_operations)
                ? explode(',', $restriction->restricted_operations)
                : $restriction->restricted_operations;

            foreach ($operations as $op) {
                $op = trim($op);
                if (empty($op)) continue;

                if (str_starts_with($op, 'leave_type_')) {
                    $val = str_replace('leave_type_', '', $op);
                    if (is_numeric($val)) $result['leave_types'][] = (int)$val;
                } elseif (str_starts_with($op, 'travel_type_')) {
                    $val = str_replace('travel_type_', '', $op);
                    if (is_numeric($val)) $result['travel_types'][] = (int)$val;
                } elseif (str_starts_with($op, 'award_type_')) {
                    $val = str_replace('award_type_', '', $op);
                    if (is_numeric($val)) $result['award_types'][] = (int)$val;
                } elseif (str_starts_with($op, 'incident_type_')) {
                    $val = str_replace('incident_type_', '', $op);
                    if (is_numeric($val)) $result['incident_types'][] = (int)$val;
                } elseif (str_starts_with($op, 'goal_type_')) {
                    $val = str_replace('goal_type_', '', $op);
                    if (is_numeric($val)) $result['goal_types'][] = (int)$val;
                } elseif (str_starts_with($op, 'assets_category_')) {
                    $val = str_replace('assets_category_', '', $op);
                    if (is_numeric($val)) $result['assets_categories'][] = (int)$val;
                } elseif (str_starts_with($op, 'product_category_')) {
                    $val = str_replace('product_category_', '', $op);
                    if (is_numeric($val)) $result['product_categories'][] = (int)$val;
                } elseif (str_starts_with($op, 'competencies_')) {
                    $val = str_replace('competencies_', '', $op);
                    if (is_numeric($val)) $result['competencies'][] = (int)$val;
                } elseif (str_starts_with($op, 'competencies2_')) {
                    $val = str_replace('competencies2_', '', $op);
                    if (is_numeric($val)) $result['competencies2'][] = (int)$val;
                } elseif (str_starts_with($op, 'dept_')) {
                    $val = str_replace('dept_', '', $op);
                    if (is_numeric($val)) $result['departments'][] = (int)$val;
                } elseif (str_starts_with($op, 'branch_')) {
                    $val = str_replace('branch_', '', $op);
                    if (is_numeric($val)) $result['branches'][] = (int)$val;
                } elseif (str_starts_with($op, 'employee_')) {
                    $result['employee_sections'][] = $op;
                } elseif (str_starts_with($op, 'report_')) {
                    $result['reports'][] = $op;
                } else {
                    $result['raw_flags'][] = $op;
                }
            }
        }

        return $result;
    }
}
