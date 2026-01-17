<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'employee_id' => 'nullable|integer',
            'status' => 'nullable|integer|in:0,1,2', // 0: Pending, 1: Accepted, 2: Rejected
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
        ];
    }
}
