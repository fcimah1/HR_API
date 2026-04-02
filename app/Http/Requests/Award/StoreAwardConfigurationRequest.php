<?php

namespace App\Http\Requests\Award;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreAwardConfigurationRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ci_erp_constants', 'category_name')->where(function ($query) use ($effectiveCompanyId) {
                    $query->where('type', \App\Models\ErpConstant::TYPE_AWARD_TYPE);
                    $query->where('company_id', $effectiveCompanyId);
                })
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم النوع مطلوب',
            'name.unique' => 'هذا النوع موجود بالفعل',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم النوع',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'خطأ في التحقق من البيانات',
            'errors' => $validator->errors()
        ], 422));
    }
}
