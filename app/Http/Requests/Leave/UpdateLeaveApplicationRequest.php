<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Schema(
 *     schema="UpdateLeaveApplicationRequest",
 *     type="object",
 *     title="Update Leave Application Request",
 *     required={"from_date", "to_date", "reason", "duty_employee_id", "is_half_day", "leave_hours", "remarks"},
 *     @OA\Property(property="from_date", type="string", format="date", description="From date"),
 *     @OA\Property(property="to_date", type="string", format="date", description="To date"),
 *     @OA\Property(property="reason", type="string", description="Reason for leave"),
 *     @OA\Property(property="duty_employee_id", type="integer", description="Duty employee ID"),
 *     @OA\Property(property="is_half_day", type="boolean", description="Is half day"),
 *     @OA\Property(property="leave_hours", type="string", description="Leave hours"),
 *     @OA\Property(property="remarks", type="string", description="Remarks")
 * )
 */

class UpdateLeaveApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('api')->check(); // تأكد من وجود مستخدم مصادق
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'from_date' => 'sometimes|date|date_format:Y-m-d',
            'to_date' => 'sometimes|date|date_format:Y-m-d|after_or_equal:from_date',
            'reason' => 'sometimes|string',
            'duty_employee_id' => 'nullable|integer|exists:ci_erp_users,user_id',
            'is_half_day' => 'sometimes|boolean',
            'leave_hours' => 'sometimes|nullable|string|max:100',
            'remarks' => 'sometimes|nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'from_date.after_or_equal' => 'تاريخ بداية الإجازة يجب أن يكون اليوم أو بعده',
            'to_date.after_or_equal' => 'تاريخ نهاية الإجازة يجب أن يكون بعد أو يساوي تاريخ البداية',
            'reason.min' => 'سبب الإجازة يجب أن يكون على الأقل 10 أحرف',
            'reason.max' => 'سبب الإجازة لا يجب أن يتجاوز 1000 حرف',
            'duty_employee_id.exists' => 'الموظف البديل المحدد غير صحيح',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if reason is provided but less than 10 characters
            if ($this->filled('reason')) {
                $reasonLength = mb_strlen(trim($this->reason));
                if ($reasonLength < 10) {
                    $validator->errors()->add('reason', 'سبب الإجازة يجب أن يكون على الأقل 10 أحرف');
                } elseif ($reasonLength > 1000) {
                    $validator->errors()->add('reason', 'سبب الإجازة لا يجب أن يتجاوز 1000 حرف');
                }
            }

            // Check if duty employee belongs to same company
            if ($this->filled('duty_employee_id')) {
                try {
                    $dutyEmployee = \App\Models\User::select('user_id')
                        ->where('user_id', $this->duty_employee_id)
                        ->where('is_active', true)
                        ->first();
                    
                    if (!$dutyEmployee) {
                        $validator->errors()->add('duty_employee_id', 'الموظف البديل يجب أن يكون من نفس الشركة ونشط');
                    }
                } catch (\Exception $e) {
                    $validator->errors()->add('duty_employee_id', 'خطأ في التحقق من الموظف البديل');
                }
            }

            // Check maximum leave duration if both dates are provided
            if ($this->filled(['from_date', 'to_date'])) {
                try {
                    $fromDate = new \DateTime($this->from_date);
                    $toDate = new \DateTime($this->to_date);
                    $duration = $toDate->diff($fromDate)->days + 1;

                    if ($duration > 30) {
                        $validator->errors()->add('to_date', 'مدة الإجازة لا يجب أن تتجاوز 30 يوماً');
                    }
                } catch (\Exception $e) {
                    $validator->errors()->add('to_date', 'التواريخ غير صحيحة');
                }
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        Log::warning('فشل تحديث طلب إجازة', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all()
        ]);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل تحديث طلب إجازة',
            'errors' => $validator->errors(),
        ], 422));
    }
}
