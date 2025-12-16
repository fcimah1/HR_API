<?php

namespace App\Http\Requests\Resignation;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateResignationRequest extends FormRequest
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
            'notice_date' => 'nullable|date|before_or_equal:resignation_date|after_or_equal:today',
            'resignation_date' => 'nullable|date|after_or_equal:notice_date',
            'reason' => 'required|string',
            'notify_send_to' => ['nullable', 'integer', new \App\Rules\CanNotifyUser()],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'notice_date.date' => 'تنسيق تاريخ الإخطار غير صحيح',
            'resignation_date.date' => 'تنسيق تاريخ الاستقالة غير صحيح',
            'notice_date.before_or_equal' => 'تاريخ الإخطار يجب أن يكون قبل أو يساوي تاريخ الاستقالة',
            'resignation_date.after_or_equal' => 'تاريخ الاستقالة يجب أن يكون بعد أو يساوي تاريخ الإخطار',
            'notice_date.after_or_equal' => 'تاريخ الإخطار يجب أن يكون بعد أو يساوي تاريخ اليوم',
            'reason.required' => 'سبب الاستقالة مطلوب',
            'reason.string' => 'سبب الاستقالة يجب أن يكون نصاً',
            'notify_send_to.integer' => 'notify_send_to يجب أن يكون رقم',
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
