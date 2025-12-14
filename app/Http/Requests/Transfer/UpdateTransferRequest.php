<?php

namespace App\Http\Requests\Transfer;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'transfer_date' => 'nullable|date',
            'transfer_department' => 'nullable|integer|exists:ci_departments,department_id',
            'transfer_designation' => 'nullable|integer|exists:ci_designations,designation_id',
            'new_salary' => 'nullable|integer',
            'reason' => 'nullable|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'transfer_date.date' => 'تنسيق تاريخ النقل غير صحيح',
            'transfer_department.exists' => 'القسم غير موجود',
            'transfer_designation.exists' => 'المسمى الوظيفي غير موجود',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'success' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors()
        ], 422);

        throw new HttpResponseException($response);
    }
}
