<?php

namespace App\Http\Requests\Country;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;


class CountrySearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:100',
            'country_id' => 'nullable|exists:ci_countries,country_id',
            'country_name' => 'nullable|string|max:100',
            'country_code' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'search.max' => 'يجب أن لا يتجاوز البحث 100 حرف',
            'country_id.exists' => 'الدولة غير موجودة',
            'country_name.max' => 'يجب أن لا يتجاوز اسم الدولة 100 حرف',
            'country_code.max' => 'يجب أن لا يتجاوز رمز الدولة 100 حرف',
        ];
    }

    public function attributes(): array
    {
        return [
            'search' => 'البحث',
            'country_id' => 'الدولة',
            'country_name' => 'اسم الدولة',
            'country_code' => 'رمز الدولة',
        ];
    }



    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}
