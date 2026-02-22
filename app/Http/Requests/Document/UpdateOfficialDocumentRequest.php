<?php

declare(strict_types=1);

namespace App\Http\Requests\Document;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateOfficialDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'license_name' => 'required|string|max:255',
            'document_type' => 'required|string|max:255',
            'license_no' => 'required|string|max:200',
            'expiry_date' => 'required|string|max:200|date|after_or_equal:today',
            'document_file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png,gif|max:10240', // 10MB max
        ];
    }

    public function messages(): array
    {
        return [
            'license_name.required' => 'يرجى إدخال اسم الموظف/الترخيص',
            'document_type.required' => 'يرجى إدخال نوع المستند',
            'license_no.required' => 'يرجى إدخال رقم المستند',
            'expiry_date.required' => 'يرجى إدخال تاريخ انتهاء الصلاحية',
            'expiry_date.date' => 'يرجى إدخال تاريخ صحيح',
            'expiry_date.after_or_equal' => 'يرجى إدخال تاريخ صحيح',
            'document_file.required' => 'يرجى اختيار ملف المستند',
            'document_file.mimes' => 'يجب أن يكون الملف من نوع: pdf, doc, docx, jpg, jpeg, png, gif',
            'document_file.max' => 'حجم الملف يجب ألا يتجاوز 10 ميجابايت',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
