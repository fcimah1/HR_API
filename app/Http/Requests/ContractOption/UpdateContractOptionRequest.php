<?php

declare(strict_types=1);

namespace App\Http\Requests\ContractOption;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContractOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contract_tax_option' => 'sometimes|nullable|integer|min:0|max:2',
            'is_fixed' => 'sometimes|required|integer|in:0,1',
            'option_title' => 'sometimes|required|string|max:255',
            'contract_amount' => 'sometimes|nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'is_fixed.in' => 'قيمة طريقة احتساب المبلغ غير صحيحة',
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
