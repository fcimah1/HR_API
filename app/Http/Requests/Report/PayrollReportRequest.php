<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب تقرير الرواتب الشهري
 * Payroll Report Request
 */
class PayrollReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_date' => 'required|string|regex:/^\d{4}-\d{2}$/', // YYYY-MM
            'employee_id' => 'nullable|integer|exists:ci_erp_users,user_id',
            'payment_method' => 'nullable|string|in:cash,bank,all',
            'job_type' => 'nullable|string|in:part_time,permanent,contract,probation,all',
            'branch_id' => 'nullable|integer|exists:ci_branchs,branch_id',
        ];
    }

    public function messages(): array
    {
        return [
            'payment_date.required' => 'الشهر مطلوب',
            'payment_date.regex' => 'صيغة الشهر غير صحيحة (YYYY-MM)',
            'employee_id.exists' => 'الموظف غير موجود',
            'payment_method.in' => 'طريقة الدفع غير صحيحة',
            'job_type.in' => 'نوع الوظيفة غير صحيح',
            'branch_id.exists' => 'الفرع غير موجود',
        ];
    }
}
