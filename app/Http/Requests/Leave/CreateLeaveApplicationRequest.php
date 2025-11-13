<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class CreateLeaveApplicationRequest extends FormRequest
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
        $user = auth()->user();
        
        return [
            'leave_type_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($user) {
                    // استخدام SimplePermissionService للحصول على معرف الشركة الفعلي
                    $permissionService = app(\App\Services\SimplePermissionService::class);
                    $companyId = $permissionService->getEffectiveCompanyId($user);
                    
                    // التحقق من وجود نوع الإجازة
                    $leaveType = \App\Models\ErpConstant::where('constants_id', $value)
                        ->where('type', \App\Models\ErpConstant::TYPE_LEAVE_TYPE)
                        ->where(function($query) use ($companyId) {
                            $query->where('company_id', $companyId)
                                  ->orWhere('company_id', 0); // الأنواع العامة
                        })
                        ->where('field_three', '1') // نشط
                        ->exists();
                    
                    if (!$leaveType) {
                        $fail('نوع الإجازة غير متاح لشركتك');
                    }
                }
            ],
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'reason' => 'required|string|max:1000|min:10',
            'duty_employee_id' => 'nullable|integer|exists:ci_erp_users,user_id',
            'is_half_day' => 'nullable|boolean',
            'leave_hours' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:1000',
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
            'from_date.required' => 'تاريخ بداية الإجازة مطلوب',
            'from_date.date' => 'تاريخ بداية الإجازة غير صحيح',
            'to_date.required' => 'تاريخ نهاية الإجازة مطلوب',
            'to_date.after_or_equal' => 'تاريخ نهاية الإجازة يجب أن يكون بعد أو يساوي تاريخ البداية',
            'reason.required' => 'سبب الإجازة مطلوب',
            'reason.min' => 'سبب الإجازة يجب أن يكون على الأقل 10 أحرف',
            'reason.max' => 'سبب الإجازة لا يجب أن يتجاوز 1000 حرف',
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
            'from_date' => 'تاريخ البداية',
            'to_date' => 'تاريخ النهاية',
            'reason' => 'سبب الإجازة',
            'duty_employee_id' => 'الموظف البديل',
            'is_half_day' => 'نصف يوم',
            'leave_hours' => 'ساعات الإجازة',
            'remarks' => 'ملاحظات',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // التحقق من الموظف البديل
            if ($this->filled('duty_employee_id')) {
                $user = $this->user();
                $permissionService = app(\App\Services\SimplePermissionService::class);
                $effectiveCompanyId = $permissionService->getEffectiveCompanyId($user);
                
                $dutyEmployee = \App\Models\User::where('user_id', $this->duty_employee_id)
                    ->where('company_id', $effectiveCompanyId)
                    ->where('is_active', true)
                    ->exists();

                if (!$dutyEmployee) {
                    $validator->errors()->add('duty_employee_id', 'الموظف البديل يجب أن يكون من نفس الشركة ونشط');
                }
            }

            // Check maximum leave duration (e.g., 30 days)
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
