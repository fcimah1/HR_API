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

        // التحقق من وجود الموظف البديل
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

        if ($dutyEmployee->user_type !== 'staff') {
            $fail('الموظف البديل يجب أن يكون موظفاً');
            return;
        }

        // جلب قسم الموظف صاحب الإجازة (وليس مقدم الطلب)
        $targetEmployeeDetails = DB::table('ci_erp_users_details')
            ->where('user_id', $employeeIdToCheck)
            ->where('company_id', $effectiveCompanyId)
            ->first(['department_id']);

        if (!$targetEmployeeDetails || !$targetEmployeeDetails->department_id) {
            $fail('لم يتم العثور على معلومات القسم للموظف صاحب الإجازة.');
            return;
        }

        $targetDepartmentId = $targetEmployeeDetails->department_id;

        Log::info('ValidDutyEmployee', [
            'requester_id' => $user->user_id,
            'target_employee_id' => $employeeIdToCheck,
            'target_department_id' => $targetDepartmentId,
            'duty_employee_id' => $value,
            'duty_department_id' => $dutyEmployee->department_id,
        ]);

        // الموظف البديل يجب أن يكون من نفس قسم الموظف صاحب الإجازة
        if ($dutyEmployee->department_id != $targetDepartmentId) {
            $fail('الموظف البديل يجب أن يكون من نفس قسم الموظف صاحب الإجازة');
            return;
        }
    }
}
