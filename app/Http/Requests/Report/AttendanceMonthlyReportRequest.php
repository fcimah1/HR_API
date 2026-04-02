<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

/**
 * Request للتحقق من بيانات تقرير الحضور الشهري
 */
class AttendanceMonthlyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', 'string', 'in:Present,Absent,Late,Early_Leaving'],
            'employee_id' => ['nullable', 'exists:ci_erp_users,user_id'],
            'branch_id' => ['nullable', 'integer'],
            'employee_ids' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'month.required' => 'الشهر مطلوب',
            'month.date_format' => 'صيغة الشهر يجب أن تكون YYYY-MM',
            'employee_id.exists' => 'الموظف غير موجود',
            'branch_id.exists' => 'الفرع غير موجود',
            'status.in' => 'حالة الحضور غير صحيحة',
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
