<?php

namespace App\DTOs\Leave;

use App\Enums\DeductedStatus;
use App\Enums\LeavePlaceEnum;
use App\Enums\NumericalStatusEnum;
use Spatie\LaravelData\Data;

class CreateHourlyLeaveDTO extends Data
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $employeeId,
        public readonly int $leaveTypeId,
        public readonly ?int $dutyEmployeeId = null,
        public readonly string $date,
        public readonly string $clockInM,
        public readonly string $clockOutM,
        public readonly string $reason,
        public readonly ?string $remarks      = null,
        public readonly ?int   $status        = null,
        public readonly ?bool  $isHalfDay     = false,
        public readonly ?int   $leaveHours    = null,
        public readonly ?string $leaveMonth   = null,
        public readonly ?string $leaveYear    = null,
        public readonly ?bool  $isDeducted    = false,
        public readonly ?bool  $place         = false,
        public readonly ?int   $createdBy     = null,
        // حقول Leave module
        public readonly ?string $countryCode  = null,
        public readonly ?float  $serviceYears = null,
        public readonly ?int    $policyId     = null,
        public readonly ?int    $tierOrder    = null,
        public readonly ?int    $paymentPercentage = null,
    ) {}

    public static function fromRequest(array $data, int $companyId, int $employeeId, ?int $createdBy = null): self
    {
        // Calculate leave hours from time strings
        $startTime = \Carbon\Carbon::parse($data['date'] . ' ' . $data['clock_in_m']);
        $endTime = \Carbon\Carbon::parse($data['date'] . ' ' . $data['clock_out_m']);
        $leaveHours = $startTime->diffInMinutes(date: $endTime) / 60;

        return new self(
            companyId: $companyId,
            employeeId: $data['employee_id'],
            leaveTypeId: $data['leave_type_id'],
            dutyEmployeeId: $data['duty_employee_id'] ?? null,
            date: $data['date'],
            clockInM: $data['clock_in_m'],
            clockOutM: $data['clock_out_m'],
            reason: $data['reason'],
            remarks: $data['remarks'] ?? null,
            status: NumericalStatusEnum::PENDING->value,
            leaveHours: $leaveHours,
            createdBy: $createdBy,
            isHalfDay: $data['is_half_day'] ?? false,
            leaveMonth: \Carbon\Carbon::parse($data['date'])->format('m'),
            leaveYear: \Carbon\Carbon::parse($data['date'])->format('Y'),
            isDeducted: $data['is_deducted'] ?? false,
            place: $data['place'] ?? LeavePlaceEnum::INSIDE->value,
            countryCode: $data['country_code'] ?? null,
            serviceYears: isset($data['service_years']) ? (float) $data['service_years'] : null,
            policyId: isset($data['policy_id'])     ? (int)   $data['policy_id']     : null,
            tierOrder: isset($data['tier_order']) ? (int)$data['tier_order'] : null,
            paymentPercentage: isset($data['payment_percentage']) ? (int)$data['payment_percentage'] : null,
        );
    }

    public function toArray(): array
    {
        $fromDate = new \DateTime($this->date);

        return [
            'company_id'      => $this->companyId,
            'employee_id'     => $this->employeeId,
            'leave_type_id'   => $this->leaveTypeId,
            'duty_employee_id' => $this->dutyEmployeeId,
            'from_date'       => $this->clockInM,
            'to_date'         => $this->clockOutM,
            'particular_date' => $this->date,
            'reason'          => $this->reason,
            'leave_hours'     => $this->leaveHours,
            'remarks'         => $this->remarks,
            'is_deducted'     => $this->isDeducted ? DeductedStatus::DEDUCTED->value : DeductedStatus::NOT_DEDUCTED->value,
            'deducted_text'   => $this->isDeducted ? DeductedStatus::DEDUCTED->labelAr() : DeductedStatus::NOT_DEDUCTED->labelAr(),
            'place'           => $this->place,
            'place_text'      => $this->place ? LeavePlaceEnum::INSIDE->labelAr() : LeavePlaceEnum::OUTSIDE->labelAr(),
            'leave_month'     => $fromDate->format('m'),
            'leave_year'      => $fromDate->format('Y'),
            'status'          => NumericalStatusEnum::PENDING->value,
            'status_text'     => NumericalStatusEnum::PENDING->labelAr(),
            // لا توجد أيام محسوبة للإستئذان بالساعات (دائماً 0)
            'calculated_days' => 0,
            'country_code'    => $this->countryCode,
            'service_years'   => $this->serviceYears,
            'policy_id'       => $this->policyId,
            'tier_order'      => $this->tierOrder,
            'payment_percentage' => $this->paymentPercentage,
            'created_at'      => now()->format('Y-m-d H:i:s'),
        ];
    }
}
