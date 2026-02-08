<?php

namespace App\Http\Requests\Branch;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_name' => 'required|string|max:255',
            'coordinates' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'branch_name.required' => 'اسم الفرع مطلوب',
            'branch_name.string' => 'اسم الفرع يجب أن يكون نصاً',
            'branch_name.max' => 'اسم الفرع يجب ألا يتجاوز 255 حرفاً',
            'coordinates.required' => 'إحداثيات الفرع مطلوبة',
            'coordinates.string' => 'إحداثيات الفرع يجب أن تكون نصاً',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors()
        ], 422));
    }
}
