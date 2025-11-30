<?php

namespace App\Http\Requests\Attendance;

use App\Enums\NumericalStatusEnum;
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
        return [
            'clock_in' => 'nullable|date_format:Y-m-d H:i:s',
            'clock_out' => 'nullable|date_format:Y-m-d H:i:s|after:clock_in',
            'status' => ['nullable', 'string', Rule::in(NumericalStatusEnum::cases())],
            'shift_id' => 'nullable|integer|exists:ci_office_shift,office_shift_id',
            'attendance_status' => 'nullable|string|in:Present,Absent',
        ];
    }

    public function messages(): array
    {
        return [
            'clock_in.date_format' => 'صيغة وقت الحضور غير صحيحة',
            'clock_out.date_format' => 'صيغة وقت الانصراف غير صحيحة',
            'clock_out.after' => 'وقت الانصراف يجب أن يكون بعد وقت الحضور',
            'status.in' => 'حالة السجل غير صحيحة',
            'attendance_status.in' => 'حالة الحضور غير صحيحة',
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
