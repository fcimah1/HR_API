<?php

namespace App\Http\Requests\Department;

use App\Models\Department;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
class UpdateDepartmentRequest extends FormRequest
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
            'department_name' => [
                'sometimes',
                'required',
                'string',
                'max:200',
                \Illuminate\Validation\Rule::unique('ci_departments', 'department_name')
                    ->ignore($this->department_id, 'department_id')
                    ->where('company_id', $effectiveCompanyId)
            ],
            'department_head' => [
                'nullable',
                'integer',
                \Illuminate\Validation\Rule::exists('ci_erp_users', 'user_id')
                    ->where('company_id', $effectiveCompanyId)
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'department_name.required' => 'اسم القسم مطلوب',
            'department_name.unique' => 'اسم القسم موجود بالفعل',
            'department_head.exists' => 'رئيس القسم غير موجود',
        ];
    }

    public function attributes(): array
    {
        return [
            'department_name' => 'اسم القسم',
            'department_head' => 'رئيس القسم',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new \Illuminate\Validation\ValidationException(
            $validator,
            response()->json([
                'message' => 'فشل التحقق من البيانات',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
