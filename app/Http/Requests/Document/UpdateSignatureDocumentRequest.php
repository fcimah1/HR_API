<?php

declare(strict_types=1);

namespace App\Http\Requests\Document;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateSignatureDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_name' => 'sometimes|required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'document_name.required' => 'يرجى إدخال اسم المستند',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
