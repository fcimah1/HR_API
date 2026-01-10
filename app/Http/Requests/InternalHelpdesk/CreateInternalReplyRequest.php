<?php

declare(strict_types=1);

namespace App\Http\Requests\InternalHelpdesk;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateInternalReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reply_text' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'reply_text.required' => 'نص الرد مطلوب',
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
