<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class DepartmentSearchRequest extends FormRequest
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
            'paginate' => 'nullable|boolean',
            'per_page' => 'nullable|integer',
            'page' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'search.max' => 'يجب أن لا يتجاوز البحث 100 حرف',
            'paginate.boolean' => 'يجب أن يكون paginate نوع boolean',
            'per_page.integer' => 'يجب أن يكون per_page نوع integer',
            'page.integer' => 'يجب أن يكون page نوع integer',
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
