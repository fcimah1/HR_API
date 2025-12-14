<?php

namespace App\Http\Requests\Resignation;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateResignationRequest extends FormRequest
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
        return [
            'employee_id' => 'nullable|integer|exists:ci_erp_users,user_id',
            'notice_date' => 'required|date',
            'resignation_date' => 'required|date|after_or_equal:notice_date',
            'reason' => 'required|string',
            'document_file' => 'nullable|string|max:255',
            'is_signed' => 'nullable|integer|in:0,1',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'notice_date.required' => 'تاريخ الإخطار مطلوب',
            'notice_date.date' => 'تنسيق تاريخ الإخطار غير صحيح',
            'resignation_date.required' => 'تاريخ الاستقالة مطلوب',
            'resignation_date.date' => 'تنسيق تاريخ الاستقالة غير صحيح',
            'resignation_date.after_or_equal' => 'تاريخ الاستقالة يجب أن يكون بعد أو يساوي تاريخ الإخطار',
            'reason.required' => 'سبب الاستقالة مطلوب',
            'employee_id.exists' => 'الموظف غير موجود',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'success' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors()
        ], 422);

        throw new HttpResponseException($response);
    }
}
