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

    protected function prepareForValidation()
    {
        if ($this->has('paginate')) {
            $this->merge([
                'paginate' => filter_var($this->paginate, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:100',
            'country_id' => 'nullable|exists:ci_countries,country_id',
            'country_name' => 'nullable|string|max:100',
            'country_code' => 'nullable|string|max:100',
            'paginate' => 'nullable|boolean',
            'per_page' => 'nullable|integer',
            'page' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'search.max' => 'يجب أن لا يتجاوز البحث 100 حرف',
            'country_id.exists' => 'الدولة غير موجودة',
            'country_name.max' => 'يجب أن لا يتجاوز اسم الدولة 100 حرف',
            'country_code.max' => 'يجب أن لا يتجاوز رمز الدولة 100 حرف',
            'paginate.boolean' => 'يجب أن يكون paginate نوع boolean',
            'per_page.integer' => 'يجب أن يكون per_page نوع integer',
            'page.integer' => 'يجب أن يكون page نوع integer',
        ];
    }

    public function attributes(): array
    {
        return [
            'search' => 'البحث',
            'country_id' => 'الدولة',
            'country_name' => 'اسم الدولة',
            'country_code' => 'رمز الدولة',
            'paginate' => 'الصفحة',
            'per_page' => 'عدد الصفحات',
            'page' => 'الصفحة',
        ];
    }



    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
