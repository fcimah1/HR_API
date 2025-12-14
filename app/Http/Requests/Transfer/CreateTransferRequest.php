<?php

namespace App\Http\Requests\Transfer;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'employee_id' => 'required|integer|exists:ci_erp_users,user_id',
            'transfer_date' => 'required|date',
            'transfer_department' => 'nullable|integer|exists:ci_departments,department_id',
            'transfer_designation' => 'nullable|integer|exists:ci_designations,designation_id',
            'reason' => 'required|string',
            'old_salary' => 'nullable|integer',
            'old_designation' => 'nullable|integer',
            'old_department' => 'nullable|integer',
            'new_salary' => 'nullable|integer',
            'old_company_id' => 'nullable|integer',
            'old_branch_id' => 'nullable|integer',
            'new_company_id' => 'nullable|integer',
            'new_branch_id' => 'nullable|integer',
            'old_currency' => 'nullable|integer',
            'new_currency' => 'nullable|integer',
            'transfer_type' => 'nullable|string|in:internal,branch,intercompany',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'الموظف مطلوب',
            'employee_id.exists' => 'الموظف غير موجود',
            'transfer_date.required' => 'تاريخ النقل مطلوب',
            'transfer_date.date' => 'تنسيق تاريخ النقل غير صحيح',
            'transfer_department.exists' => 'القسم غير موجود',
            'transfer_designation.exists' => 'المسمى الوظيفي غير موجود',
            'reason.required' => 'سبب النقل مطلوب',
            'transfer_type.in' => 'نوع النقل يجب أن يكون internal أو branch أو intercompany',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'success' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors()
        ], 422);

        throw new HttpResponseException($response);
    }
}
