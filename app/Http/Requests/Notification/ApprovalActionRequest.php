<?php

namespace App\Http\Requests\Notification;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class ApprovalActionRequest extends FormRequest
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
            'module_option' => 'required|string',
            'module_key_id' => 'required|string',
            'status' => 'required|in:approve,approved,reject,rejected',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'module_option.required' => 'يرجى تحديد نوع الوحدة',
            'module_key_id.required' => 'يرجى تحديد رقم الطلب',
            'status.required' => 'يرجى تحديد الإجراء (موافقة أو رفض)',
            'status.in' => 'الإجراء غير صحيح',
        ];
    }

    public function attributes(): array
    {
        return [
            'module_option' => 'وحدة',
            'module_key_id' => 'رقم الطلب',
            'status' => 'إجراء',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        Log::warning('فشل فى الموافقة على الطلب', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all()
        ]);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل فى الموافقة على الطلب',
            'errors' => $validator->errors(),
        ], 422));
    }
}
