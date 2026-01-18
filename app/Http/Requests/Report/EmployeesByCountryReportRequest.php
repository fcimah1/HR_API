<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class EmployeesByCountryReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'country_id' => 'nullable', // integer or 'all'
            'status' => 'nullable|string|in:active,inactive,left,all',
        ];
    }

    // messages
    public function messages(): array
    {
        return [
            'country_id.required' => 'يجب اختيار الدولة',
            'status.required' => 'يجب اختيار الحالة',
        ];
    }

    // attributes
    public function attributes(): array
    {
        return [
            'country_id' => 'الدولة',
            'status' => 'الحالة',
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
