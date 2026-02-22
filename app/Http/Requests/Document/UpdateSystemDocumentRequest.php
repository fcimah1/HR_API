<?php

declare(strict_types=1);

namespace App\Http\Requests\Document;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateSystemDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_name' => 'required|string|max:255',
            'document_type' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'document_name.required' => 'يرجى إدخال اسم المستند',
            'document_type.required' => 'يرجى إدخال نوع المستند',
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
