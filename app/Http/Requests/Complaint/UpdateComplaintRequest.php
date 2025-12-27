<?php

namespace App\Http\Requests\Complaint;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateComplaintRequest extends FormRequest
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
            'complaint_date' => 'required|date|before_or_equal:today',
            'description' => 'nullable|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.max' => 'عنوان الشكوى يجب ألا يتجاوز 255 حرف',
            'description.max' => 'يجب ألا يتجاوز معرفات الأشخاص المشتكى عليهم (مفصولة بفاصلة) 255 حرف',
            'title.required' => 'عنوان الشكوى مطلوب',
            'complaint_date.date' => 'تنسيق تاريخ الشكوى غير صحيح',
            'complaint_date.before_or_equal' => 'تاريخ الشكوى يجب ألا يتجاوز تاريخ اليوم',
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
