<?php

namespace App\Http\Requests\AdvanceSalary;

use App\Services\LoanEligibilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateTierBasedAdvanceSalaryRequest extends FormRequest
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
            // Employee identification
            'employee_id' => [
                'nullable',
                'integer',
                new \App\Rules\CanRequestForEmployee(),
            ],

            // Employee info (display fields - validated but not used for calculations)
            'employee_name' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'division' => 'nullable|string|max:255',
            'monthly_salary' => 'nullable|numeric|min:0',

            // Tier and loan details
            'tier_id' => 'required|integer|exists:ci_loan_policy_tiers,tier_id',
            'loan_amount' => 'nullable|numeric|min:0',
            'requested_months' => 'required|integer|min:1',
            'installment_amount' => 'nullable|numeric|min:0',

            // Other fields
            'reason' => 'required|string|max:1000',
            'guarantor_id' => 'nullable|integer|exists:ci_erp_users,user_id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tier_id.required' => 'نوع القرض/السلفة مطلوب',
            'tier_id.exists' => 'نوع القرض/السلفة غير صالح',
            'requested_months.required' => 'عدد أشهر التقسيط مطلوب',
            'requested_months.min' => 'عدد الأشهر يجب أن يكون 1 على الأقل',
            'reason.required' => 'السبب مطلوب',
            'reason.max' => 'السبب لا يجب أن يتجاوز 1000 حرف',
            'guarantor_id.exists' => 'الكفيل غير موجود في النظام',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'tier_id' => 'نوع القرض/السلفة',
            'requested_months' => 'عدد أشهر التقسيط',
            'reason' => 'السبب',
            'guarantor_id' => 'الكفيل',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->any()) {
                return; // Skip custom validation if basic validation failed
            }

            $user = $this->user();
            $employeeId = $this->input('employee_id', $user->user_id);
            $tierId = $this->input('tier_id');
            $requestedMonths = $this->input('requested_months');

            // Get tier
            $tier = \App\Models\LoanPolicyTier::find($tierId);
            if (!$tier) {
                $validator->errors()->add('tier_id', 'نوع القرض/السلفة غير صالح');
                return;
            }

            // Validate months doesn't exceed max
            if ($requestedMonths > $tier->max_months) {
                $validator->errors()->add('requested_months', "عدد الأشهر يتجاوز الحد الأقصى المسموح ({$tier->max_months} شهور)");
            }

            // Get employee salary
            $employee = \App\Models\User::find($employeeId);
            if (!$employee) {
                $validator->errors()->add('employee_id', 'الموظف غير موجود');
                return;
            }

            $eligibilityService = app(LoanEligibilityService::class);
            $salary = $eligibilityService->getEmployeeSalary($employee);

            if ($salary <= 0) {
                $validator->errors()->add('employee_id', 'لم يتم تحديد راتب الموظف في النظام');
                return;
            }

            // Validate 50% cap
            if (!$tier->isValidMonths($salary, $requestedMonths)) {
                $loanAmount = $tier->calculateLoanAmount($salary);
                $monthlyInstallment = round($loanAmount / $requestedMonths, 2);
                $maxDeduction = round($salary * 0.50, 2);
                $validator->errors()->add(
                    'requested_months',
                    "القسط الشهري (" . number_format($monthlyInstallment, 2) . ") يتجاوز 50% من الراتب (" . number_format($maxDeduction, 2) . ")"
                );
            }

            // Check date window (Day 7-21)
            $currentDay = (int) now()->format('d');
            if ($currentDay < 7 || $currentDay > 21) {
                $validator->errors()->add('tier_id', 'لا يمكن تقديم الطلب إلا بين الأسبوع الثاني والثالث من الشهر (يوم 7 إلى 21)');
            }

            // Check no active loan
            $hasActiveLoan = \App\Models\AdvanceSalary::where('employee_id', $employeeId)
                ->where('company_id', $user->company_id)
                ->where('status', 1)
                ->whereColumn('total_paid', '<', 'advance_amount')
                ->exists();

            if ($hasActiveLoan) {
                $validator->errors()->add('tier_id', 'لديك قرض/سلفة قائم لم يتم سداده بالكامل');
            }

            // Check no pending request
            $hasPending = \App\Models\AdvanceSalary::where('employee_id', $employeeId)
                ->where('company_id', $user->company_id)
                ->where('status', 0)
                ->exists();

            if ($hasPending) {
                $validator->errors()->add('tier_id', 'لديك طلب قرض/سلفة قيد الانتظار');
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
}
