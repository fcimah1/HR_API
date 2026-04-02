<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class ProductCategoryRequest extends FormRequest
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
            'category_name' => ['required', 'string', 'max:255',
            Rule::unique('ci_erp_constants', 'category_name')
            ->where('type', 'product_category')
            ->where('company_id', $effectiveCompanyId)
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'category_name.required' => 'اسم الفئة مطلوب',
            'category_name.unique' => 'اسم الفئة موجود بالفعل',
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
