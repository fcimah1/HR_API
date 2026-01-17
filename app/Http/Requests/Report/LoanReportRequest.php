<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

/**
 * Request عام للتحقق من بيانات التقارير
 */
class LoanReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'nullable|integer|exists:ci_erp_users,user_id',
            'staffs' => 'nullable|array',
            'staffs.*' => 'nullable|integer|exists:ci_erp_users,user_id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'month' => 'nullable|string|in:Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec',
            'year' => 'nullable|integer|min:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.exists' => 'الموظف غير موجود',
            'year.min' => 'السنة يجب أن تكون أكبر من أو تساوي 2000',
            'month.string' => 'الشهر يجب أن يكون نص',
            'month.in' => 'الشهر يجب أن يكون بين يناير و ديسمبر',
            'month.required' => 'الشهر مطلوب',
            'year.required' => 'السنة مطلوبة',
            'start_date.date' => 'تاريخ البداية يجب أن يكون تاريخ',
            'end_date.date' => 'تاريخ النهاية يجب أن يكون تاريخ',
            'start_date.required' => 'تاريخ البداية مطلوب',
            'end_date.required' => 'تاريخ النهاية مطلوب',
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
