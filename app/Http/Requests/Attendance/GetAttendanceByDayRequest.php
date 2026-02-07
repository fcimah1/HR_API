<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class GetAttendanceByDayRequest extends FormRequest
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
        $permissionService = app(\App\Services\SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId(Auth::user());
        return [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('ci_erp_users', 'user_id')->where(function ($query) use ($effectiveCompanyId) {
                    $query->where('company_id', $effectiveCompanyId);
                })
            ],
            'attendance_date' => 'nullable|date|date_format:Y-m-d',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.integer' => 'رقم الموظف يجب أن يكون رقمًا',
            'employee_id.exists' => 'رقم الموظف غير موجود',
            'attendance_date.date' => 'تاريخ الحضور يجب أن يكون تاريخًا صحيحًا',
            'attendance_date.date_format' => 'تاريخ الحضور يجب أن يكون بصيغة Y-m-d',
        ];
    }

    public function attributes(): array
    {
        return [
            'employee_id' => 'رقم الموظف',
            'attendance_date' => 'تاريخ الحضور',
        ];
    }

    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
