<?php

namespace App\Http\Requests\Employee;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SetApproversRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $permissionService = app(\App\Services\SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId(Auth::user());

        return [
            'approval_level02' => [
                'nullable',
                'integer',
                Rule::exists('ci_erp_users', 'user_id')->where(function ($query) use ($effectiveCompanyId) {
                    $query->where('company_id', $effectiveCompanyId);
                })
            ],
            'approval_level03' => [
                'nullable',
                'integer',
                Rule::exists('ci_erp_users', 'user_id')->where(function ($query) use ($effectiveCompanyId) {
                    $query->where('company_id', $effectiveCompanyId);
                })
            ],
            'approval_level01' => [
                'nullable',
                'integer',
                Rule::exists('ci_erp_users', 'user_id')->where(function ($query) use ($effectiveCompanyId) {
                    $query->where('company_id', $effectiveCompanyId);
                })
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'approval_level01.exists' => 'المعتمد المختار غير صالح',
            'approval_level02.exists' => 'المعتمد المختار غير صالح',
            'approval_level03.exists' => 'المعتمد المختار غير صالح',
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
