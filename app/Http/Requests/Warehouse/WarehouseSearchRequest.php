<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class WarehouseSearchRequest extends FormRequest
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
            'warehouse_name' => 'nullable|string',
            'city' => 'nullable|string',
            'country' => 'nullable|integer',
            'paginate' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_name.string' => 'اسم المستودع يجب ان يكون نص',
            'city.string' => 'المدينة يجب ان تكون نص',
            'country.integer' => 'الدولة يجب ان تكون رقم',
        ];
    }

    public function attributes(): array
    {
        return [
            'warehouse_name' => 'اسم المستودع',
            'city' => 'المدينة',
            'country' => 'الدولة',
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
