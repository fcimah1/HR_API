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
        public readonly ?bool $isHalfDay = false,
        public readonly ?int $leaveHours = null,
        public readonly ?string $remarks = null,
        public readonly ?string $leaveMonth = null,
        public readonly ?string $leaveYear = null,  
        public readonly ?int $status = null,
    ) {}

    public static function fromRequest(array $data, int $companyId, int $employeeId): self
    {
        //get leave hours from calculate from_date and to_date
        $fromDate = new \DateTime($data['from_date']);
        $toDate = new \DateTime($data['to_date']);
        $leaveHours = $fromDate->diff($toDate)->days + 1;
        return new self(
            companyId: $companyId,
            employeeId: $employeeId,
            leaveTypeId: $data['leave_type_id'],
            fromDate: $data['from_date'],
            toDate: $data['to_date'],
            particularDate: $data['particular_date'] ?? null,
            reason: $data['reason'],
            dutyEmployeeId: $data['duty_employee_id'] ?? null,
            isHalfDay: $data['is_half_day'] ?? false,
            leaveHours: $leaveHours,
            remarks: $data['remarks'] ?? null,  
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
            'is_half_day' => $this->isHalfDay,
            'leave_hours' => $this->leaveHours,
            'remarks' => $this->remarks,
            'leave_month' => $fromDate->format('m'),
            'leave_year' => $fromDate->format('Y'),
            'status' => NumericalStatusEnum::PENDING->value, // Pending by default
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
