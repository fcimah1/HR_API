<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\JobTypeEnum;
use App\Enums\PaymentMethodEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayslipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $details = $this->employee?->user_details;

        return [
            'payslip_id' => $this->payslip_id,
            'company_id' => $this->company_id,
            'staff_id' => $this->staff_id,
            'staff_name' => $this->employee?->full_name ?? ($this->employee ? trim(($this->employee->first_name ?? '') . ' ' . ($this->employee->last_name ?? '')) : null),
            'employee_number' => $details?->employee_id,
            'branch_id' => $details?->branch_id,
            'branch_name' => $details?->branch?->branch_name,
            'job_type' => $details?->job_type,
            'job_type_text' => JobTypeEnum::tryTranslate($details?->job_type),
            'salary_month' => $this->salary_month,
            'basic_salary' => $this->basic_salary,
            'total_allowances' => $this->total_allowances,
            'total_statutory_deductions' => $this->total_statutory_deductions,
            'loan_amount' => $this->loan_amount,
            'unpaid_leave_days' => $this->unpaid_leave_days,
            'unpaid_leave_deduction' => $this->unpaid_leave_deduction,
            'net_salary' => $this->net_salary,
            'salary_payment_method' => $this->salary_payment_method,
            'salary_payment_method_text' => PaymentMethodEnum::tryTranslate($this->salary_payment_method),
            'status' => $this->status,
            'status_text' => $this->status_text ?? null,
            'created_at' => $this->created_at,
            'allowances' => $this->whenLoaded('allowances'),
            'deductions' => $this->whenLoaded('deductions'),
        ];
    }
}
