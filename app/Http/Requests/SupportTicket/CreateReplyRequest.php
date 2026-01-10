<?php

declare(strict_types=1);

namespace App\Http\Requests\SupportTicket;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reply_text' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reply_text.required' => 'نص الرد مطلوب',
            'reply_text.max' => 'نص الرد يجب ألا يتجاوز 5000 حرف',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'خطأ في البيانات المدخلة',
            'message_en' => 'Validation error',
            'errors' => $validator->errors(),
        ], 422));
    }
}
