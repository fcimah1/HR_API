<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendenceStatus;
use App\Services\SimplePermissionService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $permissionService = app(SimplePermissionService::class);
        $companyId = $permissionService->getEffectiveCompanyId(Auth::user());

        return [
            'clock_in' => 'nullable|date_format:Y-m-d H:i:s',
            'clock_out' => 'nullable|date_format:Y-m-d H:i:s|after:clock_in',
            'status' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if (!AttendenceStatus::getValue($value)) {
                        $fail('حالة السجل غير صحيحة');
                    }
                }
            ],
            'shift_id' => [
                'nullable',
                'integer',
                Rule::exists('ci_office_shifts', 'office_shift_id')->where('company_id', $companyId)
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'clock_in.date_format' => 'صيغة وقت الحضور غير صحيحة',
            'clock_out.date_format' => 'صيغة وقت الانصراف غير صحيحة',
            'clock_out.after' => 'وقت الانصراف يجب أن يكون بعد وقت الحضور',
            'status.in' => 'حالة السجل غير صحيحة',
            'shift_id.exists' => 'الفرع غير موجود',
            'shift_id.integer' => 'الفرع يجب أن يكون رقمًا',

        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل تحديث سجل الحضور',
            'errors' => $validator->errors(),
        ], 422));
    }
}
