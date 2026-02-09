<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="EndOfServiceResource",
 *     title="EndOfServiceResource",
 *     description="Resource for End of Service calculation",
 *     @OA\Property(property="calculation_id", type="integer", example=1),
 *     @OA\Property(property="employee_id", type="integer", example=10),
 *     @OA\Property(property="employee_name", type="string", example="أحمد محمد"),
 *     @OA\Property(property="employee_code", type="string", example="EMP001"),
 *     @OA\Property(property="hire_date", type="string", format="date", example="2020-01-15"),
 *     @OA\Property(property="termination_date", type="string", format="date", example="2026-02-09"),
 *     @OA\Property(property="termination_type", type="string", example="resignation"),
 *     @OA\Property(property="service_years", type="integer", example=6),
 *     @OA\Property(property="service_months", type="integer", example=0),
 *     @OA\Property(property="service_days", type="integer", example=25),
 *     @OA\Property(property="basic_salary", type="number", format="float", example=5000.00),
 *     @OA\Property(property="allowances", type="number", format="float", example=1500.00),
 *     @OA\Property(property="total_salary", type="number", format="float", example=6500.00),
 *     @OA\Property(property="gratuity_amount", type="number", format="float", example=22750.00),
 *     @OA\Property(property="leave_compensation", type="number", format="float", example=2166.67),
 *     @OA\Property(property="total_compensation", type="number", format="float", example=24916.67),
 *     @OA\Property(property="is_approved", type="boolean", example=false),
 *     @OA\Property(property="notes", type="string", example="ملاحظات"),
 *     @OA\Property(property="calculated_at", type="string", format="datetime", example="2026-02-09 14:00:00")
 * )
 */
class EndOfServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $employee = $this->whenLoaded('employee');
        $calculator = $this->whenLoaded('calculator');

        return [
            'calculation_id' => $this->calculation_id,
            'employee_id' => $this->employee_id,
            'employee_name' => $employee ? ($employee->first_name . ' ' . $employee->last_name) : null,
            'hire_date' => $this->hire_date?->format('Y-m-d'),
            'termination_date' => $this->termination_date?->format('Y-m-d'),
            'termination_type' => $this->termination_type,
            'termination_type_label' => $this->getTerminationTypeLabel(),
            'service_years' => $this->service_years,
            'service_months' => $this->service_months,
            'service_days' => $this->service_days,
            'basic_salary' => (float)$this->basic_salary,
            'allowances' => (float)$this->allowances,
            'total_salary' => (float)$this->total_salary,
            'gratuity_amount' => (float)$this->gratuity_amount,
            'leave_compensation' => (float)$this->leave_compensation,
            'notice_compensation' => (float)$this->notice_compensation,
            'total_compensation' => (float)$this->total_compensation,
            'unused_leave_days' => $this->unused_leave_days,
            'is_approved' => (bool)$this->is_approved,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'calculated_by' => $this->calculated_by,
            'calculator_name' => $calculator ? ($calculator->first_name . ' ' . $calculator->last_name) : null,
            'calculated_at' => $this->calculated_at?->format('Y-m-d H:i:s'),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function getTerminationTypeLabel(): string
    {
        return match ($this->termination_type) {
            'resignation' => 'استقالة',
            'termination' => 'إنهاء خدمة',
            'end_of_contract' => 'انتهاء عقد',
            default => $this->termination_type,
        };
    }
}
