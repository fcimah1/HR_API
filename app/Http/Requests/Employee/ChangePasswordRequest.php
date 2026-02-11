<?php

namespace App\Http\Requests\Employee;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ChangePasswordRequest extends FormRequest
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
        return [
            'password' => 'required|string|min:6|max:255',
            'confirm_password' => 'required|string|same:password'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون على الأقل 6 أحرف',
            'password.max' => 'كلمة المرور يجب أن تكون أقل من 255 حرف',
            'confirm_password.required' => 'تأكيد كلمة المرور مطلوب',
            'confirm_password.same' => 'تأكيد كلمة المرور يجب أن يطابق كلمة المرور'
        ];
    }

    public function attributes(): array
    {
        return [
            'password' => 'كلمة المرور',
            'confirm_password' => 'تأكيد كلمة المرور',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}
