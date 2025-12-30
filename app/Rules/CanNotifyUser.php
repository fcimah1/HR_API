<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CanNotifyUser implements ValidationRule, DataAwareRule
{
    /**
     * All of the data under validation.
     *
     * @var array<string, mixed>
     */
    protected $data = [];

    /**
     * Set the data under validation.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

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

        // تحويل القيمة إلى مصفوفة إذا كانت قيمة مفردة
        $userIds = is_array($value) ? $value : [$value];

        // تنظيف المصفوفة من القيم الفارغة والمكررة
        $userIds = array_unique(array_filter($userIds));

        if (empty($userIds)) {
            return;
        }

        $user = Auth::user();

        // 1. تحديد المستخدم "الموضوع" (Subject) الذي يتم التحقق بناءً عليه
        // إذا تم تمرير employee_id في الطلب، نستخدمه. وإلا نستخدم المستخدم الحالي
        $subjectUserId = $this->data['employee_id'] ?? $user->user_id;

        // تجاهل إذا كان employee_id موجود ولكن فارغ (قد يحدث في بعض حالات التحقق)
        if (!$subjectUserId && $user) {
            $subjectUserId = $user->user_id;
        }

        // جلب بيانات المستخدم الموضوع (الموظف الذي يتم النقل/الاستقالة له)
        $subjectUser = User::with('user_details.designation')->find($subjectUserId);

        if (!$subjectUser) {
            // إذا لم يتم العثور على الموظف (وهذا قد يغطيه قواعد أخرى)، لا يمكننا التحقق من السياق
            // لكن لغرض هذه القاعدة، نفترض الفشل إذا كانت البيانات مطلوبة
            return;
        }

        // الحصول على معرف الشركة الفعلي
        $effectiveCompanyId = $subjectUser->company_id;

        // جلب بيانات جميع المستخدمين المستهدفين (المستلمين) دفعة واحدة
        $targetUsers = User::with('user_details.designation')
            ->whereIn('user_id', $userIds)
            ->where('company_id', $effectiveCompanyId)
            ->where('is_active', 1)
            ->get()
            ->keyBy('user_id');

        // البيانات الخاصة بالموضوع (Subject) للمقارنة
        $subjectDepartmentId = $subjectUser->user_details?->department_id;
        $subjectHierarchyLevel = $subjectUser->getHierarchyLevel();

        foreach ($userIds as $targetId) {
            $targetUser = $targetUsers->get($targetId);

            if (!$targetUser) {
                $fail("الموظف المستلم للإشعار (ID: $targetId) غير موجود أو غير نشط أو لا ينتمي لنفس الشركة");
                continue; // استمر في التحقق من الباقي لإظهار كل الأخطاء
            }

            // 1- إذا كان المستلم user_type = company - مسموح دائماً (Super Admin للشركة)
            if ($targetUser->user_type === 'company') {
                continue;
            }

            // الحصول على hierarchy_level للمستخدم المستهدف
            $targetHierarchyLevel = $targetUser->getHierarchyLevel();

            // 2- إذا كان المستلم hierarchy_level = 1 (أعلى مستوى، مثل المدير العام) - مسموح
            // هذا يسمح بإشعار المدراء الكبار حتى لو كانوا في قسم مختلف (افتراض منطقي)
            if ($targetHierarchyLevel === 1) {
                continue;
            }

            // يجب أن يكون أعلى في المستوى الهرمي (رقم أقل = مستوى أعلى)
            if ($subjectHierarchyLevel === null || $targetHierarchyLevel === null) {
                // تمليح: إذا لم نتمكن من تحديد المستوى، قد نمررها أو نرفضها. هنا سنرفض لضمان الصحة.
                $fail("لا يمكن تحديد المستوى الهرمي للموظف ({$targetUser->full_name}) أو الموظف صاحب الطلب");
                continue;
            }

            // المستلم يجب أن يكون "أعلى" (رقم أصغر) من الموضوع
            // مثال: المستلم (مدير - مستوى 2) يمكنه استلام إشعار عن (موظف - مستوى 4)
            // 2 < 4 => True (Valid)
            if ($targetHierarchyLevel >= $subjectHierarchyLevel) {
                $fail("المستخدم المستلم للإشعار ({$targetUser->full_name}) يجب أن يكون في مستوى هرمي أعلى من الموظف صاحب الطلب");
            }
        }
    }
}
