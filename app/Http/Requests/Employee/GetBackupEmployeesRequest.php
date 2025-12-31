<?php

namespace App\Http\Requests\Employee;

use App\Rules\CanRequestForEmployee;
use Illuminate\Foundation\Http\FormRequest;

class GetBackupEmployeesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled in Service/Controller via PermissionService
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_employee_id' => ['nullable', 'integer', new CanRequestForEmployee()],
            'search' => ['nullable', 'string', 'max:255'],
            'employee_id' => ['nullable', 'integer', 'exists:ci_erp_users,user_id'],
        ];
    }

    public function messages(): array
    {
        return [
            'target_employee_id.required' => 'الموظف المستهدف مطلوب',
            'target_employee_id.integer' => 'الموظف المستهدف يجب أن يكون رقمًا صحيحًا',
            'target_employee_id.exists' => 'الموظف المستهدف غير موجود',
            'search.max' => 'البحث يجب أن يحتوي على 255 حرفًا أو أقل',
            'employee_id.integer' => 'الموظف يجب أن يكون رقمًا صحيحًا',
            'employee_id.exists' => 'الموظف غير موجود',
        ];
    }

    public function attributes(): array
    {
        return [
            'target_employee_id' => 'الموظف المستهدف',
            'search' => 'البحث',
            'employee_id' => 'الموظف',
        ];
    }

    // public function prepareForValidation()
    // {
    //     $this->merge([
    //         'target_employee_id' => $this->input('target_employee_id'),
    //         'search' => $this->input('search'),
    //         'employee_id' => $this->input('employee_id'),
    //     ]);
    // }


    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'data' => $validator->errors()
        ], 422));
    }
}
