<?php

namespace App\Http\Requests\Employee;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateEmployeeRequest extends BaseEmployeeRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:ci_erp_users,email'],
            'username' => ['required', 'string', 'max:255', 'unique:ci_erp_users,username'],
            'password' => ['required', 'string', 'min:6'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'department_id' => ['required', 'integer', 'exists:ci_departments,department_id'],
            'designation_id' => ['required', 'integer', 'exists:ci_designations,designation_id'],
            'office_shift_id' => ['required', 'integer', 'exists:ci_office_shifts,office_shift_id'],
            'user_role_id' => ['required', 'integer', 'exists:ci_staff_roles,role_id'], 
            'reporting_manager' => ['nullable', 'integer', 'exists:ci_erp_users,user_id'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'integer', 'exists:ci_currencies,currency_id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'الاسم الأول مطلوب',
            'first_name.string' => 'الاسم الأول يجب أن يكون نص',
            'first_name.max' => 'الاسم الأول لا يجب أن يتجاوز 255 حرف',
            'last_name.required' => 'الاسم الأخير مطلوب',
            'last_name.string' => 'الاسم الأخير يجب أن يكون نص',
            'last_name.max' => 'الاسم الأخير لا يجب أن يتجاوز 255 حرف',
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email' => 'البريد الإلكتروني غير صحيح',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل',
            'username.required' => 'اسم المستخدم مطلوب',
            'username.unique' => 'اسم المستخدم مستخدم بالفعل',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل',
            'department_id.required' => 'القسم مطلوب',
            'department_id.exists' => 'القسم المحدد غير موجود',
            'designation_id.required' => 'المسمى الوظيفي مطلوب',
            'designation_id.exists' => 'المسمى الوظيفي المحدد غير موجود',
            'gender.in' => 'الجنس يجب أن يكون ذكر أو أنثى',
            'basic_salary.numeric' => 'الراتب الأساسي يجب أن يكون رقم',
            'basic_salary.min' => 'الراتب الأساسي لا يمكن أن يكون سالب',
            'shift_id.required' => 'الوردية مطلوبة',
            'shift_id.exists' => 'الوردية المحددة غير موجودة',
            'role_id.required' => 'الدور مطلوب',
            'role_id.exists' => 'الدور المحدد غير موجود',
            'reporting_manager.exists' => 'المدير المباشر المحدد غير موجود',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'الاسم الأول',
            'last_name' => 'الاسم الأخير',
            'email' => 'البريد الإلكتروني',
            'username' => 'اسم المستخدم',
            'password' => 'كلمة المرور',
            'contact_number' => 'رقم الهاتف',
            'gender' => 'الجنس',
            'department_id' => 'القسم',
            'designation_id' => 'المسمى الوظيفي',
            'basic_salary' => 'الراتب الأساسي',
            'currency_id' => 'العملة',
            'is_active' => 'الحالة',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'بيانات غير صحيحة',
            'errors' => $validator->errors(),
        ], 422));
    }
}
