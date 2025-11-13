<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware and controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'from_date' => 'sometimes|date|after_or_equal:today',
            'to_date' => 'sometimes|date|after_or_equal:from_date',
            'reason' => 'sometimes|string|max:1000|min:10',
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
            // Check if duty employee belongs to same company
            if ($this->filled('duty_employee_id')) {
                $user = $this->user();
                $dutyEmployee = \App\Models\User::where('user_id', $this->duty_employee_id)
                    ->where('company_id', $user->company_id)
                    ->where('is_active', true)
                    ->exists();

                if (!$dutyEmployee) {
                    $validator->errors()->add('duty_employee_id', 'الموظف البديل يجب أن يكون من نفس الشركة ونشط');
                }
            }

            // Check maximum leave duration if both dates are provided
            if ($this->filled(['from_date', 'to_date'])) {
                $fromDate = new \DateTime($this->from_date);
                $toDate = new \DateTime($this->to_date);
                $duration = $toDate->diff($fromDate)->days + 1;

                if ($duration > 30) {
                    $validator->errors()->add('to_date', 'مدة الإجازة لا يجب أن تتجاوز 30 يوماً');
                }
            }
        });
    }
}
