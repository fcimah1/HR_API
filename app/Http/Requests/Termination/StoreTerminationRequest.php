<?php

namespace App\Http\Requests\Termination;

use App\Services\SimplePermissionService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreTerminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permissionService = app(SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId(Auth::user());

        return [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('ci_erp_users', 'user_id')->where(function ($query) use ($effectiveCompanyId) {
                    $query->where('company_id', $effectiveCompanyId);
                })
            ],
            'notice_date' => 'required|date',
            'termination_date' => 'required|date',
            'reason' => 'required|string',
            'document_file' => 'nullable|file|mimes:pdf|max:5120',
            'status' => 'nullable|integer',
            'is_signed' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'الموظف مطلوب',
            'employee_id.exists' => 'الموظف غير موجود في هذه الشركة',
            'notice_date.required' => 'تاريخ الإشعار مطلوب',
            'termination_date.required' => 'تاريخ إنهاء الخدمة مطلوب',
            'reason.required' => 'سبب إنهاء الخدمة مطلوب',
            'document_file.mimes' => 'يجب أن يكون الملف بصيغة: pdf',
            'document_file.max' => 'حجم الملف لا يجب أن يتجاوز 5 ميجابايت',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'status' => false,
                'message' => 'البيانات غير صالحة',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
