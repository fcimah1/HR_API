<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class CreateLeaveAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'leave_type_id' => 'required|integer|exists:ci_erp_constants,constants_id',
            'adjust_hours' => 'required|string|max:200',
            'reason_adjustment' => 'required|string|max:1000|min:10',
            'adjustment_date' => 'nullable|date',
            'duty_employee_id' => 'nullable|integer|exists:ci_erp_users,user_id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'leave_type_id.required' => 'نوع الإجازة مطلوب',
            'leave_type_id.exists' => 'نوع الإجازة المحدد غير صحيح',
            'adjust_hours.required' => 'ساعات التسوية مطلوبة',
            'adjust_hours.max' => 'ساعات التسوية لا يجب أن تتجاوز 200 حرف',
            'reason_adjustment.required' => 'سبب التسوية مطلوب',
            'reason_adjustment.min' => 'سبب التسوية يجب أن يكون على الأقل 10 أحرف',
            'reason_adjustment.max' => 'سبب التسوية لا يجب أن يتجاوز 1000 حرف',
            'duty_employee_id.exists' => 'الموظف البديل المحدد غير صحيح',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'leave_type_id' => 'نوع الإجازة',
            'adjust_hours' => 'ساعات التسوية',
            'reason_adjustment' => 'سبب التسوية',
            'adjustment_date' => 'تاريخ التسوية',
            'duty_employee_id' => 'الموظف البديل',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if leave type belongs to user's company
            if ($this->filled('leave_type_id')) {
                $user = $this->user();
                $leaveType = \App\Models\ErpConstant::where('constants_id', $this->leave_type_id)
                    ->where('type', \App\Models\ErpConstant::TYPE_LEAVE_TYPE)
                    ->where(function($query) use ($user) {
                        $query->where('company_id', $user->company_id)
                              ->orWhere('company_id', 0); // General types
                    })
                    ->where('field_three', '1') // Active
                    ->exists();

                if (!$leaveType) {
                    $validator->errors()->add('leave_type_id', 'نوع الإجازة غير متاح لشركتك');
                }
            }

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

            // Validate adjust_hours format (should be numeric)
            if ($this->filled('adjust_hours')) {
                if (!is_numeric($this->adjust_hours)) {
                    $validator->errors()->add('adjust_hours', 'ساعات التسوية يجب أن تكون رقماً');
                } elseif ((float) $this->adjust_hours < 0) {
                    $validator->errors()->add('adjust_hours', 'ساعات التسوية يجب أن تكون أكبر من أو تساوي صفر');
                } elseif ((float) $this->adjust_hours > 1000) {
                    $validator->errors()->add('adjust_hours', 'ساعات التسوية لا يجب أن تتجاوز 1000 ساعة');
                }
            }
        });
    }
}
