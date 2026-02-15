<?php

namespace App\Http\Requests\Recruitment\Job;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ApplyJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'job_id' => 'required|exists:ci_rec_jobs,job_id',
            'message' => 'nullable|string',
            'job_resume' => 'required|file|mimes:pdf,doc,docx,jpeg,png,jpg|max:5120', // 5MB limit
        ];
    }

    public function messages(): array
    {
        return [
            'job_id.required' => 'معرف الوظيفة مطلوب',
            'job_id.exists' => 'الوظيفة غير موجودة',
            'job_resume.required' => 'السيرة الذاتية مطلوبة',
            'job_resume.file' => 'يجب أن يكون الملف صالحاً',
            'job_resume.mimes' => 'صيغة الملف غير مدعومة (pdf, doc, docx, jpeg, png, jpg)',
            'job_resume.max' => 'حجم الملف يجب ألا يتجاوز 5 ميجابايت',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل فى التحقق من البيانات',
            'errors' => $validator->errors()
        ], 422));
    }
}
