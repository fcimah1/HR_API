<?php

declare(strict_types=1);

namespace App\Http\Requests\ContractOption;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ContractOptionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'search.max' => 'يجب ألا يتجاوز البحث 200 حرف',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new \Illuminate\Validation\ValidationException(
            $validator,
            response()->json([
                'message' => 'فشل التحقق من البيانات',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
