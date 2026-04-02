<?php

namespace App\Http\Requests\CustodyClearance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class CustodyClearanceSearchRequest extends FormRequest
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
            'employee_id' => 'nullable|integer',
            'status' => 'nullable|string|in:pending,approved,rejected',
            'clearance_type' => 'nullable|string',
            'paginate' => 'nullable|boolean',
            'per_page' => 'nullable|integer',
            'page' => 'nullable|integer',
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
