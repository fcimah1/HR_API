<?php

namespace App\Http\Requests\Complaint;

use App\Rules\CanRequestComplaintForEmployee;
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
            'complaint_date' => 'required|date|before_or_equal:today',
            'complaint_against' => ['nullable', 'array', 'max:255', new CanRequestComplaintForEmployee()], // comma-separated user IDs (e.g., "703,725")
            'description' => 'nullable|string',
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
            'complaint_against.array' => 'يجب تحديد معرفات الأشخاص المشتكى عليه',
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
