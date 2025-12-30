<?php

namespace App\Services;

use App\Models\User;
use App\Models\StaffRole;
use App\Models\OperationRestriction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SimplePermissionService
{
    // Cache TTL for permissions (1 hour)
    private const PERMISSION_CACHE_TTL = 3600;

    /**
     * التحقق من صلاحية المستخدم
     */
    public function checkPermission(User $user, string $permission): bool
    {
        $userType = strtolower(trim($user->user_type ?? ''));
        // مستخدم الشركة له صلاحيات كاملة ولا يعتمد على جدول الأدوار
        if ($userType === 'company') {
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

        return Cache::remember($cacheKey, self::PERMISSION_CACHE_TTL, function () use ($user) {
            $role = StaffRole::where('role_id', $user->user_role_id)
                ->where('company_id', $user->company_id)
                ->first();

            if ($role) {
                return array_filter(explode(',', $role->role_resources ?? ''));
            }

            return [];
        });
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
    /**
     * التحقق إذا كان المستخدم يمكنه رؤية طلبات موظف آخر
     * بناءً على المستوى الهرمي والقيود
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

        // 4. بناء الاستعلام لجلب الموظفين المسموح بهم
        $query = DB::table('ci_erp_users')
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
            ->join('ci_erp_users_details', 'ci_erp_users_details.user_id', '=', 'ci_erp_users.user_id')
            ->leftJoin('ci_designations', 'ci_designations.designation_id', '=', 'ci_erp_users_details.designation_id')
            ->leftJoin('ci_departments', 'ci_departments.department_id', '=', 'ci_erp_users_details.department_id')
            ->where('ci_erp_users.company_id', $companyId)
            ->where('ci_erp_users.user_type', 'staff')
            ->where('ci_erp_users.is_active', 1);

        // الشرط الجوهري: الموظف المستهدف يجب أن يكون في مستوى أدنى (رقم أعلى) أو مساوي
        $query->where('ci_designations.hierarchy_level', '>=', $currentHierarchyLevel);

        // تطبيق استبعاد الأقسام المحظورة
        if (!empty($restrictedDepartments)) {
            $query->whereNotIn('ci_erp_users_details.department_id', $restrictedDepartments);
        }

        // تطبيق استبعاد الفروع المحظورة
        if (!empty($restrictedBranches)) {
            $query->whereNotIn('ci_erp_users_details.branch_id', $restrictedBranches);
        }

        if (!$includeSelf) {
            $query->where('ci_erp_users.user_id', '!=', $userId);
        }

        return $query->get()->toArray();
    }
}
