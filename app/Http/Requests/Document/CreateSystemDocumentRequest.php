<?php

declare(strict_types=1);

namespace App\Http\Requests\Document;

use App\Services\SimplePermissionService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CreateSystemDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permissionService = app(SimplePermissionService::class);
        $companyId = $permissionService->getEffectiveCompanyId(Auth::user());
        return [
            'department_id' => ['required', 'integer', 
            Rule::exists('ci_departments', 'department_id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'document_name' => 'required|string|max:255',
            'document_type' => 'required|string|max:255',
            'document_file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240', // 10MB max
        ];
    }

    public function messages(): array
    {
        return [
            'department_id.required' => 'يرجى اختيار القسم',
            'department_id.exists' => 'القسم المختار غير موجود',
            'document_name.required' => 'يرجى إدخال اسم المستند',
            'document_type.required' => 'يرجى إدخال نوع المستند',
            'document_file.required' => 'يرجى اختيار ملف المستند',
            'document_file.mimes' => 'يجب أن يكون الملف من نوع: pdf, doc, docx, jpg, jpeg, png',
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
