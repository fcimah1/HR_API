<?php

namespace App\Http\Requests\Attendance;

use App\Enums\PunchTypeEnum;
use App\Enums\VerifyModeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'branch_id' => 'required|integer|exists:ci_branchs,branch_id',
            'employee_id' => 'required|string|max:50',
            'punch_time' => 'required|date_format:Y-m-d H:i:s',
            'verify_mode' => ['required', 'integer', Rule::in(VerifyModeEnum::cases())], // use enum VerifyModeEnum
            'punch_type' => ['required', 'integer', Rule::in(PunchTypeEnum::cases())], // use enum PunchTypeEnum
            'work_code' => 'nullable|integer', // Optional work/project code
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
            'branch_id.exists' => 'رقم الفرع غير صحيح',
            'employee_id.required' => 'رقم الموظف مطلوب',
            'employee_id.string' => 'رقم الموظف يجب أن يكون نص',
            'employee_id.max' => 'رقم الموظف يجب أن يكون أقل من 50 حرف',
            'punch_time.required' => 'وقت البصمة مطلوب',
            'punch_time.date_format' => 'صيغة الوقت غير صحيحة (Y-m-d H:i:s)',
            'verify_mode.required' => 'نوع التحقق مطلوب',
            'verify_mode.integer' => 'نوع التحقق يجب أن يكون رقم صحيح',
            'verify_mode.in' => 'نوع التحقق غير صالح (0=كلمة مرور, 1=بصمة, 2=بطاقة, 3=كلمة مرور+بصمة, 4=بطاقة+بصمة, 15=وجه)',
            'punch_type.required' => 'نوع البصمة مطلوب',
            'punch_type.integer' => 'نوع البصمة يجب أن يكون رقم صحيح',
            'punch_type.in' => 'نوع البصمة غير صالح (0=حضور, 1=انصراف, 2=خروج استراحة, 3=عودة استراحة, 4=حضور إضافي, 5=انصراف إضافي, 255=غير محدد)',
            'work_code.integer' => 'كود العمل يجب أن يكون رقم صحيح',
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
