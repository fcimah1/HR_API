<?php

namespace App\Http\Requests\AdvanceSalary;

use App\Enums\oneTimeDeduct;
use App\Enums\SalaryEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

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
            'employee_id' => [
                'nullable',
                'integer',
                new \App\Rules\CanRequestForEmployee(),
            ],
            'salary_type' => 'required|string|in:' . SalaryEnum::LOAN->value . ',' . SalaryEnum::ADVANCE->value,
            'month_year' => [
                'required',
                'string',
                'regex:/^\d{4}-(0[1-9]|1[0-2])$/', // Format: YYYY-MM
            ],
            'advance_amount' => 'required|numeric|min:1|max:999999',
            'one_time_deduct' => 'required|string|in:' . oneTimeDeduct::TRUE->value . ',' . oneTimeDeduct::FALSE->value,
            'monthly_installment' => 'required|numeric|min:0|max:999999', // required if one_time_deduct is 0
            'reason' => 'required|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'salary_type.required' => 'نوع الطلب مطلوب',
            'salary_type.in' => 'نوع الطلب يجب أن يكون مرتب مسبقا أو سلفة',
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

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'success' => false,
            'message' => 'فشل التحقق من البيانات',
            'errors' => $validator->errors()
        ], 422);

        throw new HttpResponseException($response);
    }
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        if ($this->has('one_time_deduct') && $this->input('one_time_deduct') == oneTimeDeduct::TRUE->value) {
            if ($this->has('advance_amount')) {
                $this->merge([
                    'monthly_installment' => $this->input('advance_amount')
                ]);
            }
        }
    }
}
