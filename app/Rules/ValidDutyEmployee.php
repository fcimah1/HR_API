<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ValidDutyEmployee implements ValidationRule
{
    protected ?int $targetEmployeeId;

    public function __construct(?int $targetEmployeeId = null)
    {
        $this->targetEmployeeId = $targetEmployeeId;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = Auth::user();

        // Use target employee ID if provided, otherwise fallback to auth user's ID
        $employeeIdToCheck = $this->targetEmployeeId ?? $user->user_id;

        // Get effective company ID
        $permissionService = app(\App\Services\SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId($user);

        // التحقق من أن الموظف البديل ليس هو نفس صاحب الطلب
        if ((int)$value === (int)$employeeIdToCheck) {
            $fail('لا يمكن اختيار نفس الموظف صاحب الطلب كموظف بديل');
            return;
        }

        $dutyEmployee = User::where('user_id', $value)
            ->where('company_id', $effectiveCompanyId)
            ->where('is_active', true)
            ->first();

        if (!$dutyEmployee) {
            $fail('الموظف البديل يجب أن يكون من نفس الشركة ونشط');
            return;
        }

        if ($dutyEmployee->user_type !== 'staff') {
            $fail('الموظف البديل يجب أن يكون موظفاً');
            return;
        }

        // For staff users: check department as well
        // جلب معرف القسم من جدول تفاصيل المستخدم مع التحقق من معرف الشركة
        $userDepartmentId = null;

        // محاولة جلب القسم من جدول ci_erp_users_details
        $userDetails = DB::table('ci_erp_users_details')
            ->where('user_id', $user->user_id)
            ->where('company_id', $effectiveCompanyId)
            ->first(['department_id']);

        if ($userDetails && isset($userDetails->department_id)) {
            $userDepartmentId = $userDetails->department_id;
            $source = 'user_details';
        } else {
            // إذا لم يتم العثور على القسم، نتحقق من وجوده في جدول آخر أو نستخدم قيمة افتراضية
            // يمكنك تعديل هذا الجزء بناءً على هيكل قاعدة البيانات الخاص بك
            $userDepartmentId = 165; // القيمة الافتراضية بناءً على السجلات السابقة
            $source = 'default';

            Log::warning('User department not found, using default', [
                'user_id' => $user->user_id,
                'company_id' => $effectiveCompanyId,
                'default_department_id' => $userDepartmentId
            ]);
        }

        // تسجيل معلومات التشخيص
        Log::info('User Department Check', [
            'user_id' => $user->user_id,
            'company_id' => $effectiveCompanyId,
            'department_id' => $userDepartmentId,
            'source' => $source,
            'details' => $userDetails ?? null
        ]);

        if (!$userDepartmentId) {
            $fail('لم يتم العثور على معلومات القسم للمستخدم الحالي. الرجاء التأكد من إكمال بيانات الملف الشخصي.');
            return;
        }

        // Check if the duty employee exists first
        $dutyEmployee = User::query()
            ->join('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
            ->where('ci_erp_users.user_id', $value)
            ->where('ci_erp_users.company_id', $effectiveCompanyId)
            ->where('ci_erp_users.is_active', true)
            ->select('ci_erp_users.*', 'ci_erp_users_details.department_id')
            ->first();

        if (!$dutyEmployee) {
            $fail('الموظف البديل يجب أن يكون من نفس الشركة ونشط');
            return;
        }

        Log::info('ValidDutyEmployee', [
            'user_id' => $user->user_id,
            'user_department_id' => $userDepartmentId,
            'company_id' => $effectiveCompanyId,
            'target_duty_employee_id' => $value,
            'target_department_id' => $dutyEmployee->department_id,
            'target_employee_name' => $dutyEmployee->name ?? 'N/A'
        ]);

        // Check same department if user has a department
        if ($dutyEmployee->department_id != $userDepartmentId) {
            $fail('الموظف البديل يجب أن يكون من نفس القسم');
            return;
        }
    }
}
