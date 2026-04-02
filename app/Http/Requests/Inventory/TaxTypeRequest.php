<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TaxTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permissionService = app(\App\Services\SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId(Auth::user());
        return [
            'tax_name' => ['required', 'string', 'max:255',
            Rule::unique('ci_erp_constants', 'category_name')
            ->where('type', 'tax_type')
            ->where('company_id', $effectiveCompanyId)
            ],
            'tax_rate' => 'required|numeric|min:0',
            'tax_type' => 'required|string|in:percentage,fixed',
        ];
    }

    public function messages(): array
    {
        return [
            'tax_name.required' => 'اسم الضريبة مطلوب',
            'tax_name.unique' => 'اسم الضريبة موجود بالفعل',
            'tax_rate.required' => 'معدل الضريبة مطلوب',
            'tax_type.required' => 'نوع الضريبة مطلوب',
            'tax_type.in' => 'نوع الضريبة يجب أن يكون نسبة مئوية أو مبلغ ثابت',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل في التحقق من البيانات',
            'errors' => $validator->errors()
        ], 422));
    }
}
