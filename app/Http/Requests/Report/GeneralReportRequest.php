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
class GeneralReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'nullable|integer|exists:ci_erp_users,user_id',
            'year' => 'nullable|integer|min:2000',
            'leave_type' => 'nullable|integer|exists:ci_erp_constants,constants_id',
            'duration_type' => 'nullable|string|in:daily,hourly',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.exists' => 'الموظف غير موجود',
            'year.min' => 'السنة يجب أن تكون أكبر من أو تساوي 2000',
            'leave_type.exists' => 'النوع غير موجود',
            'duration_type.in' => 'النوع غير صحيح',
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
