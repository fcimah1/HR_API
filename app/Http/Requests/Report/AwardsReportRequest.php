<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class AwardsReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'employee_id' => 'nullable|integer|exists:ci_erp_users,user_id',
            'employee_ids' => 'nullable|array', // For internal use (hierarchy)
            'employee_ids.*' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'تاريخ البداية مطلوب',
            'start_date.date_format' => 'صيغة تاريخ البداية غير صحيحة (YYYY-MM-DD)',
            'end_date.required' => 'تاريخ النهاية مطلوب',
            'end_date.date_format' => 'صيغة تاريخ النهاية غير صحيحة (YYYY-MM-DD)',
            'end_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية',
            'employee_id.exists' => 'الموظف غير موجود',
        ];
    }
}
