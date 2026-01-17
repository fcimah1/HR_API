<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ResignationsReportRequest extends FormRequest
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
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'employee_id' => 'nullable|integer', // Manual check in Service
            'status' => 'nullable|integer|in:0,1,2', // 0: Pending, 1: Accepted, 2: Rejected
            'employee_ids' => 'nullable|array', // For internal use
            'employee_ids.*' => 'integer',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'start_date' => 'تاريخ االبداية',
            'end_date' => 'تاريخ النهاية',
            'employee_id' => 'الموظف',
            'status' => 'الحالة',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'يجب ادخال تاريخ البداية',
            'end_date.required' => 'يجب ادخال تاريخ النهاية',
            'end_date.after_or_equal' => 'يجب ادخال تاريخ النهاية بعد تاريخ البداية',
            'employee_id.integer' => 'يجب ادخال رقم صحيح',
            'status.integer' => 'يجب ادخال رقم صحيح',
            'status.in' => 'يجب ادخال رقم صحيح',
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
