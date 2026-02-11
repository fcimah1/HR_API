<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileInfoRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $employeeId = $this->route('id');
        
        return [
            'username' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('ci_erp_users', 'username')->ignore($employeeId, 'user_id')
            ],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('ci_erp_users', 'email')->ignore($employeeId, 'user_id')
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'username.string' => 'اسم المستخدم يجب أن يكون نص',
            'username.max' => 'اسم المستخدم يجب أن يكون أقل من 255 حرف',
            'username.unique' => 'اسم المستخدم مستخدم بالفعل',
            'email.email' => 'البريد الإلكتروني يجب أن يكون صحيح',
            'email.max' => 'البريد الإلكتروني يجب أن يكون أقل من 255 حرف',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل'
        ];
    }

 
     public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        Log::warning('validation errors',[
            'success' => false,
            'status_code' => 422,
            'url' => url()->current(),
            'message' => 'Validation errors',
            'data' => $validator->errors()
        ]);
        throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'data' => $validator->errors()
        ], 422));
    }
}
