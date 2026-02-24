<?php

declare(strict_types=1);

namespace App\Http\Requests\Payslip;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class PayslipDraftActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'salary_month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'staff_id' => 'nullable|integer|exists:ci_erp_users,user_id',
            'salary_payment_method' => 'nullable|string',
            'branch_id' => 'nullable|integer|exists:ci_branchs,branch_id',
            'job_type' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'salary_month.required' => 'الشهر مطلوب',
            'salary_month.regex' => 'صيغة الشهر غير صحيحة (YYYY-MM)',
            'staff_id.exists' => 'الموظف غير موجود',
            'branch_id.exists' => 'الفرع غير موجود',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
