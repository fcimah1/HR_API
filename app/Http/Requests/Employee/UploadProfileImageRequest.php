<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class UploadProfileImageRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'profile_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'profile_image.required' => 'صورة الملف الشخصي مطلوبة',
            'profile_image.image' => 'الملف يجب أن يكون صورة',
            'profile_image.mimes' => 'صيغة الصورة يجب أن تكون: jpeg, png, jpg, gif',
            'profile_image.max' => 'حجم الصورة يجب أن يكون أقل من 2 ميجابايت'
        ];
    }
}