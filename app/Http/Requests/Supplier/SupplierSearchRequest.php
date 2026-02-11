<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SupplierSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if ($this->has('paginate')) {
            $this->merge([
                'paginate' => filter_var($this->paginate, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'supplier_name' => 'nullable|string',
            'email' => 'nullable|string',
            'city' => 'nullable|string',
            'paginate' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_name.string' => 'اسم المورد يجب ان يكون نص',
            'email.string' => 'البريد الالكتروني يجب ان يكون نص',
            'city.string' => 'المدينة يجب ان تكون نص',
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_name' => 'اسم المورد',
            'email' => 'البريد الالكتروني',
            'city' => 'المدينة',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'فشل فى التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
