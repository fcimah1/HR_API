<?php

namespace App\Http\Requests\LeaveAdjustment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
// No logging on validation failures for adjustments to avoid noisy logs


/**
 * @OA\Schema(
 *     schema="CreateLeaveAdjustmentRequest",
 *     type="object",
 *     title="Create Leave Adjustment Request",
 *     required={"leave_type_id", "adjustment_date", "duty_employee_id", "adjust_hours", "reason_adjustment"},
 *     @OA\Property(property="leave_type_id", type="integer", description="Leave type ID"),
 *     @OA\Property(property="adjustment_date", type="string", format="date", description="Adjustment date"),
 *     @OA\Property(property="duty_employee_id", type="integer", description="Duty employee ID"),
 *     @OA\Property(property="adjust_hours", type="number", format="float", description="Adjustment hours"),
 *     @OA\Property(property="reason_adjustment", type="string", description="Reason for adjustment")
 * )
 */

class CreateLeaveAdjustmentRequest extends FormRequest
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
        $user = Auth::user();
        return [
            'leave_type_id' => 'required|exists:ci_erp_constants,constants_id',
            'adjustment_date' => [
                'required',
                'date',
                'after_or_equal:today' // يمنع التواريخ الماضية
            ],
            'duty_employee_id' => [
                'nullable',
                'exists:ci_erp_users,user_id',
                'different:employee_id' // يمنع أن يكون الموظف البديل هو نفس الموظف
            ],
            'adjust_hours' => [
                'required',
                'numeric',
                'min:0.5', // على الأقل نصف ساعة
                // 'max:24'   // كحد أقصى 24 ساعة
            ],
            'reason_adjustment' => 'required|string|max:500',
        ];
    }

    // public function withValidator($validator)
    // {
    //     $validator->after(function ($validator) {
    //         if ($validator->errors()->any()) {
                
    //             Log::warning('فشل التحقق من صحة طلب إنشاء تسوية إجازة', [
    //                 'errors' => $validator->errors()->toArray(),
    //                 'input' => $this->all()
    //             ]);
    //             throw new HttpResponseException(response()->json([
    //                 'success' => false,
    //                 'message' => 'فشل التحقق من صحة طلب إنشاء تسوية إجازة',
    //                 'errors' => $validator->errors(),
    //             ], 422));
    //         }
    //     });
    // }


    
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل التحقق من صحة طلب إنشاء تسوية إجازة',
            'errors' => $validator->errors(),
        ], 422));
    }

    public function messages(): array
    {
        return [
            'leave_type_id.required' => 'يجب تحديد نوع الإجازة',
            'leave_type_id.exists' => 'نوع الإجازة غير صالح',
            'adjustment_date.required' => 'يجب تحديد تاريخ التعديل',
            'adjustment_date.date' => 'يجب إدخال تاريخ صحيح',
            'adjustment_date.after_or_equal' => 'لا يمكن تحديد تاريخ ماضي',
            'duty_employee_id.exists' => 'الموظف البديل غير موجود',
            'duty_employee_id.different' => 'لا يمكن اختيار الموظف نفسه كبديل',
            'adjust_hours.required' => 'يجب تحديد عدد ساعات التعديل',
            'adjust_hours.numeric' => 'يجب إدخال رقم صحيح أو عشري',
            'adjust_hours.min' => 'يجب أن لا يقل عدد الساعات عن 0.5',
            'adjust_hours.max' => 'يجب أن لا يزيد عدد الساعات عن 24 ساعة',
            'reason_adjustment.required' => 'يجب إدخال سبب التعديل',
            'reason_adjustment.string' => 'يجب إدخال نص صالح',
            'reason_adjustment.max' => 'يجب أن لا يتجاوز سبب التعديل 500 حرف'
        ];
    }

}
