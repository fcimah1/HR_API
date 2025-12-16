<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

class CanNotifyUser implements ValidationRule
{
    /**
     * التحقق من إمكانية إرسال إشعار للمستخدم المحدد
     * يجب أن يكون المستخدم:
     * 1- user_type = company لنفس الشركة
     * 2- hierarchy_level = 1 (أعلى مستوى)
     * 3- في نفس القسم وأعلى في المستوى الهرمي
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // إذا كانت القيمة فارغة، لا حاجة للتحقق (nullable)
        if (empty($value)) {
            return;
        }

        $user = Auth::user();

        // الحصول على معرف الشركة الفعلي
        $permissionService = app(\App\Services\SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId($user);

        // التحقق من وجود المستخدم المستلم في نفس الشركة
        $targetUser = User::with('user_details.designation')
            ->where('user_id', $value)
            ->where('company_id', $effectiveCompanyId)
            ->where('is_active', 1)
            ->first();

        if (!$targetUser) {
            $fail('الموظف المستلم للإشعار غير موجود أو غير نشط');
            return;
        }

        // 1- إذا كان user_type = company لنفس الشركة - مسموح
        if ($targetUser->user_type === 'company') {
            return;
        }

        // الحصول على hierarchy_level للمستخدم المستهدف
        $targetHierarchyLevel = $targetUser->getHierarchyLevel();

        // 2- إذا كان hierarchy_level = 1 (أعلى مستوى) - مسموح
        if ($targetHierarchyLevel === 1) {
            return;
        }

        // 3- التحقق من أنه في نفس القسم وأعلى في المستوى الهرمي
        $currentUser = User::with('user_details.designation')->find($user->user_id);
        $currentHierarchyLevel = $currentUser->getHierarchyLevel();
        $currentDepartmentId = $currentUser->user_details?->department_id;
        $targetDepartmentId = $targetUser->user_details?->department_id;

        // يجب أن يكون في نفس القسم
        if ($currentDepartmentId !== $targetDepartmentId) {
            $fail('المستخدم المستلم للإشعار يجب أن يكون في نفس القسم');
            return;
        }

        // يجب أن يكون أعلى في المستوى الهرمي (رقم أقل = مستوى أعلى)
        if ($targetHierarchyLevel === null || $currentHierarchyLevel === null) {
            $fail('لا يمكن تحديد المستوى الهرمي للموظف');
            return;
        }

        if ($targetHierarchyLevel >= $currentHierarchyLevel) {
            $fail('المستخدم المستلم للإشعار يجب أن يكون في مستوى هرمي أعلى');
            return;
        }
    }
}
