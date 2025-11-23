<?php

namespace App\Services;

use App\Models\User;
use App\Models\StaffRole;

class SimplePermissionService
{
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

        $role = StaffRole::where('role_id', $user->user_role_id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$role) {
            return false;
        }

        // التحقق من وجود الصلاحية في role_resources
        $permissions = array_filter(explode(',', $role->role_resources ?? ''));
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
     * الحصول على جميع صلاحيات المستخدم
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

        $role = StaffRole::where('role_id', $user->user_role_id)
            ->where('company_id', $user->company_id)
            ->first();

        if ($role) {
            return array_filter(explode(',', $role->role_resources ?? ''));
        }

        return [];
    }

    /**
     * التحقق من نوع المستخدم
     */
    public function isCompanyOwner(User $user): bool
    {
        return $user->company_id == 0 && $user->user_role_id == 0;
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
}
