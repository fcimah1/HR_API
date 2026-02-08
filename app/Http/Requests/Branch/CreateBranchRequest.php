<?php

namespace App\Http\Requests\Branch;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_name' => [
                'required',
                'string',
                'max:255',
                'unique:ci_branchs,branch_name',
            ],
            'coordinates' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    // Validate coordinates format (lat,lng or WKT)
                    if (!preg_match('/^-?\d+\.?\d*,\s*-?\d+\.?\d*$/', $value) &&
                        !preg_match('/^POINT\(([^ ]+) ([^ ]+)\)/i', $value) &&
                        !preg_match('/^POLYGON/i', $value)) {
                        $fail('صيغة الإحداثيات غير صحيحة');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_name.required' => 'اسم الفرع مطلوب',
            'branch_name.string' => 'اسم الفرع يجب أن يكون نصاً',
            'branch_name.max' => 'اسم الفرع يجب ألا يتجاوز 255 حرفاً',
            'branch_name.unique' => 'اسم الفرع موجود بالفعل',
            'coordinates.required' => 'إحداثيات الفرع مطلوبة',
            'coordinates.string' => 'إحداثيات الفرع يجب أن تكون نصاً',
            'coordinates.format' => 'صيغة الإحداثيات غير صحيحة',
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
