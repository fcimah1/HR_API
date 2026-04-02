<?php

declare(strict_types=1);

namespace App\Http\Requests\Document;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class GetOfficialDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'search.string' => 'البحث يجب أن يكون نصًا',
            'search.max' => 'البحث يجب أن يكون 255 حرفًا على الأكثر',
            'per_page.integer' => 'عدد العناصر في الصفحة يجب أن يكون رقمًا',
            'per_page.min' => 'عدد العناصر في الصفحة يجب أن يكون 1 على الأقل',
            'per_page.max' => 'عدد العناصر في الصفحة يجب أن يكون 100 على الأكثر',
            'page.integer' => 'رقم الصفحة يجب أن يكون رقمًا',
            'page.min' => 'رقم الصفحة يجب أن يكون 1 على الأقل',
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
