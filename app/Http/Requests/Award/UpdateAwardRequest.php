<?php

namespace App\Http\Requests\Award;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateAwardRequest extends FormRequest
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
        $permissionService = app(\App\Services\SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId(Auth::user());

        return [
            'employee_id' => [
                'nullable',
                'integer',
                Rule::exists('ci_erp_users', 'user_id')->where(function ($query) use ($effectiveCompanyId) {
                    $query->where('company_id', $effectiveCompanyId);
                })
            ],
            'award_type_id' => [
                'nullable',
                'integer',
                Rule::exists('ci_erp_constants', 'constants_id')->where(function ($query) use ($effectiveCompanyId) {
                    $query->where(function ($q) use ($effectiveCompanyId) {
                        $q->where('company_id', $effectiveCompanyId)
                            ->orWhere('company_id', 0);
                    })->where('type', \App\Models\ErpConstant::TYPE_AWARD_TYPE);
                })
            ],
            'award_date' => 'nullable|date',
            'gift_item' => 'nullable|string|max:255',
            'cash_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'award_information' => 'nullable|string',
            'award_file' => 'nullable|file|max:5120|mimes:jpeg,png,jpg,gif,svg,pdf,doc,docx', // Max 5MB
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.exists' => 'الموظف المختار غير صالح',
            'award_type_id.exists' => 'نوع المكافئة المختار غير صالح',
            'award_file.file' => 'يجب أن يكون الملف ملفاً',
            'award_file.max' => 'حجم الملف لا يجب أن يتجاوز 5 ميجابايت',
            'award_file.mimes' => 'يجب أن يكون الملف من نوع: jpeg, png, jpg, gif, svg, pdf, doc, docx',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors()
        ], 422));
    }
}
