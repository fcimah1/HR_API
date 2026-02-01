<?php

namespace App\Http\Requests\Employee;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UploadDocumentRequest extends FormRequest
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
            'document_name' => 'required|string|max:255',
            'document_type' => 'required|string|max:100',
            'document_file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
            'expiration_date' => 'nullable|date|after:today'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'document_name.required' => 'اسم المستند مطلوب',
            'document_name.max' => 'اسم المستند يجب أن يكون أقل من 255 حرف',
            'document_type.required' => 'نوع المستند مطلوب',
            'document_type.max' => 'نوع المستند يجب أن يكون أقل من 100 حرف',
            'document_file.required' => 'ملف المستند مطلوب',
            'document_file.file' => 'يجب أن يكون ملف صحيح',
            'document_file.mimes' => 'صيغة الملف يجب أن تكون: pdf, doc, docx, jpg, jpeg, png',
            'document_file.max' => 'حجم الملف يجب أن يكون أقل من 5 ميجابايت',
            'expiration_date.date' => 'تاريخ انتهاء الصلاحية يجب أن يكون تاريخ صحيح',
            'expiration_date.after' => 'تاريخ انتهاء الصلاحية يجب أن يكون بعد اليوم'
        ];
    }

    public function attributes(): array
    {
        return [
            'document_name' => 'اسم المستند',
            'document_type' => 'نوع المستند',
            'document_file' => 'ملف المستند',
            'expiration_date' => 'تاريخ انتهاء الصلاحية'
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}