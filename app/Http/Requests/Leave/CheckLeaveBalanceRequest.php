<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Schema(
 *     schema="CheckLeaveBalanceRequest",
 *     type="object",
 *     title="Check Leave Balance Request",
 *     required={"leave_type_id", "employee_id"},
 *     @OA\Property(property="leave_type_id", type="integer", description="Leave type ID"),
 *     @OA\Property(property="employee_id", type="integer", description="Employee ID")
 * )
 */

class CheckLeaveBalanceRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // نوع الإجازة اختياري؛ إذا لم يُرسل سنحسب رصيد جميع أنواع الإجازات
            'leave_type_id' => 'nullable|integer|exists:ci_erp_constants,constants_id',
            // يمكن للمدير/الشركة تمرير employee_id لعرض ملخص رصيد موظف آخر
            'employee_id' => 'nullable|integer|exists:ci_erp_users,user_id',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل التحقق من صحة بيانات طلب فحص رصيد الإجازة',
            'errors' => $validator->errors(),
        ], 422));
    }

    public function messages(): array
    {
        return [
            'leave_type_id.required' => 'يجب تحديد نوع الإجازة',
            'leave_type_id.integer' => 'معرف نوع الإجازة يجب أن يكون رقمًا صحيحًا',
            'leave_type_id.exists' => 'نوع الإجازة غير صالح',
            'employee_id.integer' => 'معرف الموظف يجب أن يكون رقمًا صحيحًا',
            'employee_id.exists' => 'الموظف المطلوب غير موجود',
        ];
    }
}