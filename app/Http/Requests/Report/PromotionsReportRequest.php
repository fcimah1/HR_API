<?php

namespace App\Http\Requests\Report;

use App\Enums\NumericalStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class PromotionsReportRequest extends FormRequest
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
        $statusValues = NumericalStatusEnum::valuesString();
        return [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'employee_id' => 'nullable|integer',
            'status' => 'nullable|integer|in:' . $statusValues, // 0: Pending, 1: Accepted, 2: Rejected
            'employee_ids' => 'nullable|array', // For internal use (hierarchy)
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
            'employee_ids' => 'الموظفين',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'تاريخ االبداية مطلوب',
            'start_date.date_format' => 'تاريخ االبداية يجب أن يكون بصيغة Y-m-d',
            'end_date.required' => 'تاريخ النهاية مطلوب',
            'end_date.date_format' => 'تاريخ النهاية يجب أن يكون بصيغة Y-m-d',
            'end_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد تاريخ االبداية',
            'employee_id.integer' => 'الموظف يجب أن يكون رقمًا',
            'status.integer' => 'الحالة يجب أن تكون رقمًا',
            'status.in' => 'الحالة يجب أن تكون من بين القيم التالية: ' . $this->statusValues,
            'employee_ids.array' => 'الموظفين يجب أن تكون مصفوفة',
            'employee_ids.*.integer' => 'الموظفين يجب أن تكون أرقامًا',
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
