<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class BulkAssignRequest extends FormRequest
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
            'asset_ids' => 'required|array|min:1',
            'asset_ids.*' => 'integer|exists:ci_assets,assets_id',
            'employee_id' => 'required|integer|exists:ci_erp_users,user_id',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'asset_ids.required' => 'Asset IDs are required',
            'asset_ids.array' => 'Asset IDs must be an array',
            'asset_ids.min' => 'At least one asset ID is required',
            'asset_ids.*.exists' => 'One or more asset IDs do not exist',
            'employee_id.required' => 'Employee ID is required',
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

