<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateAssetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'assets_category_id' => 'required|integer|min:1',
            'brand_id' => 'required|integer|min:1',
            'name' => 'required|string|max:255',
            'company_asset_code' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date_format:Y-m-d',
            'invoice_number' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'warranty_end_date' => 'nullable|date_format:Y-m-d',
            'asset_note' => 'nullable|string',
            'asset_image' => 'nullable|string|max:255',
            'is_working' => 'nullable|boolean',
            'employee_id' => 'nullable|integer|exists:ci_erp_users,user_id',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'assets_category_id.required' => 'Asset category is required',
            'assets_category_id.integer' => 'Asset category must be a valid ID',
            'brand_id.required' => 'Asset brand is required',
            'brand_id.integer' => 'Asset brand must be a valid ID',
            'name.required' => 'Asset name is required',
            'purchase_date.date_format' => 'Purchase date must be in YYYY-MM-DD format',
            'warranty_end_date.date_format' => 'Warranty end date must be in YYYY-MM-DD format',
            'employee_id.exists' => 'Selected employee does not exist',
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

