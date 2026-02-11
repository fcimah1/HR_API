<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProductSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if ($this->has('search') && !$this->has('product_name')) {
            $this->merge(['product_name' => $this->search]);
        }
        if ($this->has('paginate')) {
            $this->merge([
                'paginate' => filter_var($this->paginate, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ]);
        }
        if ($this->has('out_of_stock')) {
            $this->merge([
                'out_of_stock' => filter_var($this->out_of_stock, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ]);
        }
        if ($this->has('expired')) {
            $this->merge([
                'expired' => filter_var($this->expired, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'product_name' => 'nullable|string',
            'search' => 'nullable|string',
            'warehouse_id' => 'nullable|integer',
            'category_id' => 'nullable|integer',
            'out_of_stock' => 'nullable|boolean',
            'expired' => 'nullable|boolean',
            'paginate' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل في التحقق من البيانات',
            'errors' => $validator->errors()
        ], 422));
    }
}
