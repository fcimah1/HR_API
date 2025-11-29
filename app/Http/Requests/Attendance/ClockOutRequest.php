<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class ClockOutRequest extends FormRequest
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
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.string' => 'إحداثيات الموقع يجب أن تكون نصاً',
            'longitude.string' => 'إحداثيات الموقع يجب أن تكون نصاً',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل تسجيل الانصراف',
            'errors' => $validator->errors(),
        ], 422));
    }
}
