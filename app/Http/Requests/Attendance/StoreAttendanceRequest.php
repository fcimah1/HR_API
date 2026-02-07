<?php

namespace App\Http\Requests\Attendance;

use App\Services\SimplePermissionService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $permissionService = app(SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId(Auth::user());

        return [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('ci_erp_users', 'user_id')->where(function ($query) use ($effectiveCompanyId) {
                    $query->where('company_id', $effectiveCompanyId);
                })
            ],
            'start_attendance_date' => 'required|date_format:Y-m-d',
            'end_attendance_date' => 'required|date_format:Y-m-d',
            'clock_in' => 'required_without:office_shift_id|date_format:H:i',
            'clock_out' => 'required_without:office_shift_id|date_format:H:i|after:clock_in',
            'office_shift_id' => [
                'required_without:clock_in,clock_out',
                'integer',
                Rule::exists('ci_office_shifts', 'office_shift_id')->where('company_id', $effectiveCompanyId)
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'حقل الموظف مطلوب',
            'employee_id.exists' => 'الموظف المحدد غير موجود',
            'start_attendance_date.required' => 'تاريخ الحضور مطلوب',
            'start_attendance_date.date_format' => 'صيغة التاريخ غير صحيحة',
            'end_attendance_date.required' => 'تاريخ الحضور مطلوب',
            'end_attendance_date.date_format' => 'صيغة التاريخ غير صحيحة',
            'clock_in.required_without' => 'وقت الحضور مطلوب',
            'clock_in.date_format' => 'صيغة وقت الحضور غير صحيحة',
            'clock_out.required_without' => 'وقت الانصراف مطلوب',
            'clock_out.date_format' => 'صيغة وقت الانصراف غير صحيحة',
            'clock_out.after' => 'وقت الانصراف يجب أن يكون بعد وقت الحضور',
            'office_shift_id.required_without' => 'الوردية مطلوبة',
            'office_shift_id.exists' => 'الوردية المحددة غير موجودة',
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
