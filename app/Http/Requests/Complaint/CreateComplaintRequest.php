<?php

namespace App\Http\Requests\Complaint;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateComplaintRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'complaint_date' => 'nullable|date',
            'complaint_against' => 'required|string',
            'description' => 'required|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'عنوان الشكوى مطلوب',
            'title.max' => 'عنوان الشكوى يجب ألا يتجاوز 255 حرف',
            'complaint_against.required' => 'يجب تحديد الشخص/الجهة المشتكى عليها',
            'description.required' => 'وصف الشكوى مطلوب',
            'complaint_date.date' => 'تنسيق تاريخ الشكوى غير صحيح',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'success' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors()
        ], 422);

        throw new HttpResponseException($response);
    }
}
