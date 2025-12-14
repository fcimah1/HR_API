<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class BiometricPunchRequest extends FormRequest
{
    /**
     * لا يحتاج تسجيل دخول - الأجهزة ترسل مباشرة
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق من البيانات
     */
    public function rules(): array
    {
        return [
            'company_id' => 'required|integer|min:1',
            'branch_id' => 'required|integer|min:0',
            'employee_id' => 'required|string|max:50',
            'punch_time' => 'required|date_format:Y-m-d H:i:s',
        ];
    }

    /**
     * رسائل الخطأ
     */
    public function messages(): array
    {
        return [
            'company_id.required' => 'رقم الشركة مطلوب',
            'company_id.integer' => 'رقم الشركة يجب أن يكون رقم صحيح',
            'branch_id.required' => 'رقم الفرع مطلوب',
            'branch_id.integer' => 'رقم الفرع يجب أن يكون رقم صحيح',
            'employee_id.required' => 'رقم الموظف مطلوب',
            'employee_id.string' => 'رقم الموظف يجب أن يكون نص',
            'employee_id.max' => 'رقم الموظف يجب أن يكون أقل من 50 حرف',
            'punch_time.required' => 'وقت البصمة مطلوب',
            'punch_time.date_format' => 'صيغة الوقت غير صحيحة (Y-m-d H:i:s)',
        ];
    }

    /**
     * معالجة فشل التحقق
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors()
        ], 422));
    }
}
