<?php

namespace App\DTOs\Leave;

use App\Enums\NumericalStatusEnum;

class CreateLeaveApplicationDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $employeeId,
        public readonly int $leaveTypeId,
        public readonly string $fromDate,
        public readonly string $toDate,
        public readonly ?string $particularDate = null,
        public readonly string $reason,
        public readonly ?int $dutyEmployeeId = null,
        public readonly ?int $leaveHours = null,
        public readonly ?string $remarks = null,
        public readonly ?string $leaveMonth = null,
        public readonly ?string $leaveYear = null,
        public readonly ?int $status = null,
        public readonly bool $place,
        public readonly bool $isHalfDay = false,
        public readonly bool $isDeducted = true,
        public readonly ?string $countryCode = null,
        public readonly ?float $serviceYears = null,
        public readonly ?int $policyId = null,
        public readonly ?int $tierOrder = null,
        public readonly ?int $paymentPercentage = null,
        public readonly ?int $createdBy = null,  // Who created this leave request
    ) {}

    public static function fromRequest(array $data, int $companyId, int $employeeId, ?int $createdBy = null): self
    {
        return new self(
            companyId: $companyId,
            employeeId: $employeeId,
            leaveTypeId: $data['leave_type_id'],
            fromDate: $data['from_date'],
            toDate: $data['to_date'],
            particularDate: (function () use ($data) {
                $fromDate = new \DateTime($data['from_date']);
                $toDate = new \DateTime($data['to_date']);
                $durationInDays = $toDate->diff($fromDate)->days + 1;
                // If leave is for single day, set particular_date to that date
                return $durationInDays === 1 ? $data['from_date'] : null;
            })(),
            reason: $data['reason'],
            dutyEmployeeId: $data['duty_employee_id'] ?? null,
            leaveHours: $data['leave_hours'] ?? null,
            remarks: $data['remarks'] ?? null,
            // احتساب شهر وسنة الإجازة من تاريخ البداية
            leaveMonth: (new \DateTime($data['from_date']))->format('m'),
            leaveYear: (new \DateTime($data['from_date']))->format('Y'),
            status: NumericalStatusEnum::PENDING->value,
            place: $data['place'] ?? false,
            isHalfDay: (bool)($data['is_half_day'] ?? false),
            isDeducted: $data['is_deducted'] ?? true,
            countryCode: $data['country_code'] ?? null,
            serviceYears: isset($data['service_years']) ? (float)$data['service_years'] : null,
            policyId: isset($data['policy_id']) ? (int)$data['policy_id'] : null,
            tierOrder: isset($data['tier_order']) ? (int)$data['tier_order'] : null,
            paymentPercentage: isset($data['payment_percentage']) ? (int)$data['payment_percentage'] : null,
            createdBy: $createdBy,  // Pass the creator ID
        );
    }

    public function toArray(): array
    {
        $fromDate = new \DateTime($this->fromDate);

        return [
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'leave_type_id' => $this->leaveTypeId,
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
            'particular_date' => $this->particularDate,
            'reason' => $this->reason,
            'duty_employee_id' => $this->dutyEmployeeId,
            'leave_hours' => $this->leaveHours,
            'remarks' => $this->remarks,
            'leave_month' => $fromDate->format('m'),
            'leave_year' => $fromDate->format('Y'),
            'status' => NumericalStatusEnum::PENDING->value, // Pending by default
            'place' => $this->place,
            'is_half_day' => $this->isHalfDay ? 1 : 0,
            'is_deducted' => $this->isDeducted,
            'country_code' => $this->countryCode,
            'service_years' => $this->serviceYears,
            'policy_id' => $this->policyId,
            'tier_order' => $this->tierOrder,
            'payment_percentage' => $this->paymentPercentage,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    public function getDurationInDays(): int
    {
        $fromDate = new \DateTime($this->fromDate);
        $toDate = new \DateTime($this->toDate);
        return $toDate->diff($fromDate)->days + 1;
    }
}
