<?php

namespace App\Http\Requests\ResidenceRenewal;

use App\Services\SimplePermissionService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreResidenceRenewalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permissionService = app(SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId(Auth::user());

        return [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('ci_erp_users', 'user_id')->where(function ($query) use ($effectiveCompanyId) {
                    $query->where('company_id', $effectiveCompanyId);
                })
            ],
            'work_permit_fee' => 'required|numeric|min:0',
            'residence_renewal_fees' => 'required|numeric|min:0',
            'penalty_amount' => 'required|numeric|min:0',
            'current_residence_expiry_date' => 'required|date',
            'is_manual_shares' => 'nullable|boolean',
            'employee_share' => 'required_if:is_manual_shares,true|nullable|numeric|min:0',
            'company_share' => 'required_if:is_manual_shares,true|nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'الموظف مطلوب',
            'employee_id.exists' => 'الموظف غير موجود في هذه الشركة',
            'work_permit_fee.required' => 'رسوم رخصة العمل مطلوبة',
            'residence_renewal_fees.required' => 'رسوم تجديد الإقامة مطلوبة',
            'penalty_amount.required' => 'قيمة المخالفة مطلوبة',
            'current_residence_expiry_date.required' => 'تاريخ انتهاء الإقامة الحالي مطلوب',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'status' => false,
                'message' => 'البيانات غير صالحة',
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $this->all();

            // Check if is_manual_shares is truly active (can be boolean or string 'true')
            $isManual = filter_var($data['is_manual_shares'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($isManual) {
                $totalFees = (float)($data['work_permit_fee'] ?? 0) +
                    (float)($data['residence_renewal_fees'] ?? 0) +
                    (float)($data['penalty_amount'] ?? 0);

                $sharesSum = (float)($data['employee_share'] ?? 0) +
                    (float)($data['company_share'] ?? 0);

                // Allow for small decimal difference (0.01)
                if (abs($totalFees - $sharesSum) > 0.01) {
                    $validator->errors()->add('employee_share', "يجب أن يكون مجموع حصة الموظف وحصة الشركة مساوياً لإجمالي المبلغ ($totalFees)");
                }
            }
        });
    }
}
