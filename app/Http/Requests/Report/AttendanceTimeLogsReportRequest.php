<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class AttendanceTimeLogsReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date|before_or_equal:today',
            'employee_id' => 'required|integer|exists:ci_erp_users,user_id',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'تاريخ البداية مطلوب',
            'start_date.date' => 'صيغة تاريخ البداية غير صحيحة',
            'end_date.required' => 'تاريخ النهاية مطلوب',
            'end_date.date' => 'صيغة تاريخ النهاية غير صحيحة',
            'end_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية',
            'end_date.before_or_equal' => 'تاريخ النهاية يجب أن يكون قبل أو يساوي تاريخ اليوم',
            'employee_id.required' => 'الموظف مطلوب',
            'employee_id.exists' => 'الموظف غير موجود',
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
