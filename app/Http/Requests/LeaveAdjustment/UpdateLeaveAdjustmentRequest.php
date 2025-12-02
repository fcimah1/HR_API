<?php

namespace App\Http\Requests\LeaveAdjustment;

use App\Models\ErpConstant;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Schema(
 *     schema="UpdateLeaveAdjustmentRequest",
 *     type="object",
 *     title="Update Leave Adjustment Request",
 *     required={"leave_type_id", "adjust_hours", "reason_adjustment", "adjustment_date", "duty_employee_id"},
 *     @OA\Property(property="leave_type_id", type="integer", description="Leave type ID"),
 *     @OA\Property(property="adjust_hours", type="number", format="float", description="Adjustment hours"),
 *     @OA\Property(property="reason_adjustment", type="string", description="Reason for adjustment"),
 *     @OA\Property(property="adjustment_date", type="string", format="date", description="Adjustment date"),
 *     @OA\Property(property="duty_employee_id", type="integer", description="Duty employee ID")
 * )
 */

class UpdateLeaveAdjustmentRequest extends FormRequest
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
            'leave_type_id' => 'sometimes|integer|exists:ci_erp_constants,constants_id',
            'adjust_hours' => 'sometimes|string|max:100',
            'reason_adjustment' => 'sometimes|string|max:1000|min:10',
            'adjustment_date' => 'sometimes|date',
            'duty_employee_id' => 'nullable|integer|exists:ci_erp_users,user_id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'leave_type_id.exists' => 'نوع الإجازة المحدد غير صحيح',
            'adjust_hours.max' => 'ساعات التسوية لا يجب أن تتجاوز 100 حرف',
            'reason_adjustment.min' => 'سبب التسوية يجب أن يكون على الأقل 10 أحرف',
            'reason_adjustment.max' => 'سبب التسوية لا يجب أن يتجاوز 1000 حرف',
            'adjustment_date.date' => 'تاريخ التسوية يجب أن يكون تاريخاً صحيحاً',
            'duty_employee_id.exists' => 'الموظف البديل المحدد غير صحيح',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if duty employee belongs to same company
            if ($this->filled('duty_employee_id')) {
                $user = $this->user();
                $dutyEmployee =  User::where('user_id', $this->duty_employee_id)
                    ->where('company_name', $user->company_name)
                    ->where('is_active', true)
                    ->exists();

                if (!$dutyEmployee) {
                    $validator->errors()->add('duty_employee_id', 'الموظف البديل يجب أن يكون من نفس الشركة ونشط');
                }
            }

            // Check if leave type belongs to user's company
            if ($this->filled('leave_type_id')) {
                $user = $this->user();
                $leaveTypes =  ErpConstant::getActiveLeaveTypesByCompanyName($user->company_name);
                $availableIds = $leaveTypes->pluck('constants_id')->toArray();
                
                if (!in_array($this->leave_type_id, $availableIds)) {
                    $validator->errors()->add('leave_type_id', 'نوع الإجازة غير متاح لشركتك');
                }
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        Log::warning('فشل تحديث تسوية إجازة', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all()
        ]);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل تحديث تسوية إجازة',
            'errors' => $validator->errors(),
        ], 422));
    }
}
