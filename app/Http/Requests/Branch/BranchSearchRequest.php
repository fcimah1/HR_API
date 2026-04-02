<?php

namespace App\Http\Requests\Branch;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class BranchSearchRequest extends FormRequest
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
            'branch_id' => 'nullable|exists:ci_branchs,branch_id',
            'paginate' => 'nullable|boolean',
            'per_page' => 'nullable|integer',
            'page' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'search.max' => 'يجب أن لا يتجاوز البحث 100 حرف',
            'branch_id.exists' => 'الفرع غير موجود',
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
