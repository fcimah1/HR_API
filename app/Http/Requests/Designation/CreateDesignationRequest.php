<?php

namespace App\Http\Requests\Designation;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateDesignationRequest extends FormRequest
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
            'department_id' => [
                'required',
                'integer',
                \Illuminate\Validation\Rule::exists('ci_departments', 'department_id')
                    ->where('company_id', $effectiveCompanyId)
            ],
            'designation_name' => [
                'required',
                'string',
                'max:200',
                \Illuminate\Validation\Rule::unique('ci_designations', 'designation_name')
                    ->where('company_id', $effectiveCompanyId)
            ],
            'hierarchy_level' => 'required|integer|min:0|max:5',
            'description' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'department_id.required' => 'معرف القسم مطلوب',
            'department_id.exists' => 'معرف القسم غير موجود',
            'designation_name.required' => 'اسم المسمى الوظيفي مطلوب',
            'designation_name.unique' => 'اسم المسمى الوظيفي موجود بالفعل',
            'hierarchy_level.required' => 'مستوى التسلسل الهرمي مطلوب',
            'hierarchy_level.integer' => 'مستوى التسلسل الهرمي يجب أن يكون عددًا صحيحًا',
            'hierarchy_level.min' => 'مستوى التسلسل الهرمي يجب أن يكون 0 على الأقل',
            'hierarchy_level.max' => 'مستوى التسلسل الهرمي يجب ألا يتجاوز 5',
            'description.string' => 'الوصف يجب أن يكون نصًا',
        ];
    }

    public function attributes(): array
    {
        return [
            'department_id' => 'معرف القسم',
            'designation_name' => 'اسم المسمى الوظيفي',
            'hierarchy_level' => 'مستوى التسلسل الهرمي',
            'description' => 'الوصف',
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
