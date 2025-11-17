<?php

namespace App\Http\Requests\AdvanceSalary;

use Illuminate\Foundation\Http\FormRequest;

class CreateAdvanceSalaryRequest extends FormRequest
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
            'salary_type' => 'required|string|in:loan,advance',
            'month_year' => [
                'required',
                'string',
                'regex:/^\d{4}-(0[1-9]|1[0-2])$/', // Format: YYYY-MM
            ],
            'advance_amount' => 'required|numeric|min:1|max:999999',
            'one_time_deduct' => 'required|string|in:0,1',
            'monthly_installment' => 'required|numeric|min:0|max:999999',
            'reason' => 'required|string|min:10|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'salary_type.required' => 'نوع الطلب مطلوب',
            'salary_type.in' => 'نوع الطلب يجب أن يكون قرض أو سلفة',
            'month_year.required' => 'الشهر والسنة مطلوبان',
            'month_year.regex' => 'صيغة الشهر والسنة غير صحيحة (يجب أن تكون: YYYY-MM)',
            'advance_amount.required' => 'المبلغ مطلوب',
            'advance_amount.numeric' => 'المبلغ يجب أن يكون رقماً',
            'advance_amount.min' => 'المبلغ يجب أن يكون أكبر من صفر',
            'advance_amount.max' => 'المبلغ يجب ألا يتجاوز 999,999',
            'one_time_deduct.required' => 'خصم لمرة واحدة مطلوب',
            'one_time_deduct.in' => 'خصم لمرة واحدة يجب أن يكون نعم أو لا',
            'monthly_installment.required' => 'القسط الشهري مطلوب',
            'monthly_installment.numeric' => 'القسط الشهري يجب أن يكون رقماً',
            'monthly_installment.min' => 'القسط الشهري يجب أن يكون صفر أو أكبر',
            'monthly_installment.max' => 'القسط الشهري يجب ألا يتجاوز 999,999',
            'reason.required' => 'السبب مطلوب',
            'reason.min' => 'السبب يجب أن يكون على الأقل 10 أحرف',
            'reason.max' => 'السبب لا يجب أن يتجاوز 1000 حرف',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'salary_type' => 'نوع الطلب',
            'month_year' => 'الشهر والسنة',
            'advance_amount' => 'المبلغ',
            'one_time_deduct' => 'خصم لمرة واحدة',
            'monthly_installment' => 'القسط الشهري',
            'reason' => 'السبب',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that monthly installment doesn't exceed advance amount
            if ($this->filled(['advance_amount', 'monthly_installment'])) {
                $advanceAmount = (float) $this->advance_amount;
                $monthlyInstallment = (float) $this->monthly_installment;

                if ($monthlyInstallment > $advanceAmount) {
                    $validator->errors()->add('monthly_installment', 'القسط الشهري لا يجب أن يتجاوز المبلغ الإجمالي');
                }
            }

            // Validate month_year is not in the past (optional business rule)
            if ($this->filled('month_year')) {
                try {
                    $requestDate = new \DateTime($this->month_year . '-01');
                    $currentDate = new \DateTime(date('Y-m-01'));
                    
                    if ($requestDate < $currentDate) {
                        $validator->errors()->add('month_year', 'لا يمكن طلب سلفة أو قرض لشهر سابق');
                    }
                } catch (\Exception $e) {
                    // Date validation already handled by regex
                }
            }

            // If one_time_deduct is '1' (Yes), monthly_installment should equal advance_amount
            if ($this->filled(['one_time_deduct', 'advance_amount', 'monthly_installment'])) {
                if ($this->one_time_deduct === '1') {
                    $advanceAmount = (float) $this->advance_amount;
                    $monthlyInstallment = (float) $this->monthly_installment;
                    
                    if ($monthlyInstallment != $advanceAmount) {
                        $validator->errors()->add('monthly_installment', 'عند اختيار خصم لمرة واحدة، يجب أن يساوي القسط الشهري المبلغ الإجمالي');
                    }
                }
            }
        });
    }
}

