<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class ClockInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'latitude' => 'nullable|string|max:50',
            'longitude' => 'nullable|string|max:50',
            'work_from_home' => 'nullable|boolean',
            'shift_id' => 'nullable|integer|exists:ci_office_shift,office_shift_id',
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.string' => 'إحداثيات الموقع يجب أن تكون نصاً',
            'longitude.string' => 'إحداثيات الموقع يجب أن تكون نصاً',
            'work_from_home.boolean' => 'العمل من المنزل يجب أن يكون قيمة صحيحة',
            'shift_id.exists' => 'الوردية المحددة غير موجودة',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل تسجيل الحضور',
            'errors' => $validator->errors(),
        ], 422));
    }
}
