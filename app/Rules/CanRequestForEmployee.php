<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CanRequestForEmployee implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = Auth::user();

        // Get effective company ID
        $permissionService = app(\App\Services\SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId($user);

        $employee = User::where('user_id', $value)
            ->where('company_id', $effectiveCompanyId)
            ->where('is_active', 1)
            ->first();

        if (!$employee) {
            $fail('الموظف المحدد يجب أن يكون من نفس الشركة ونشط');
            return;
        }

        if ($employee->user_type === 'company') {
            return;
        }elseif ($employee->user_type === 'staff') {
 
            // For staff users: check employee exists first
            $targetEmployee = User::query()
                ->join('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
                ->where('ci_erp_users.user_id', $value)
                ->where('ci_erp_users.company_id', $effectiveCompanyId)
                ->where('ci_erp_users.is_active', 1)
                ->select('ci_erp_users.*', 'ci_erp_users_details.department_id')
                ->first();

            if ($targetEmployee) {

                if (!$targetEmployee) {
                    $fail('الموظف المحدد يجب أن يكون من نفس الشركة ونشط');
                    return;
                }

                // Company users can create requests for any employee in their company
                if ($user->user_type === 'company') {
                    return;
                }

                // Allow employees to request for themselves
                if ($user->user_id === $targetEmployee->user_id) {
                    return;
                }

                // Then check department
                $userDepartmentId = $user->user_details?->department_id;
                if ($targetEmployee->department_id != $userDepartmentId) {
                    $fail('الموظف المحدد يجب أن يكون من نفس القسم');
                    return;
                }

                // Finally check hierarchy permissions
                if (!$user->canMakeRequestFor($targetEmployee)) {
                    $fail('ليس لديك صلاحية لتقديم طلب لهذا الموظف. يجب أن تكون في مستوى هرمي أعلى أو المدير المباشر.');
                }
            }
        }else{
            $fail('الموظف المحدد يجب أن يكون موظفاً ');
            return;
    }
}
}
