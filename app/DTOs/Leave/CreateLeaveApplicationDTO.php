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
        public readonly ?bool $isDeducted,
        public readonly ?bool $place,
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
                // If leave is for single day, set particular_date to current date (submission date)
                return $durationInDays === 1 ? date('Y-m-d') : null;
            })(),
            reason: $data['reason'],
            dutyEmployeeId: $data['duty_employee_id'] ?? null,
            isHalfDay: $data['is_half_day'] ?? false,
            leaveHours: $data['leave_hours'] ?? (function () use ($data) {
                $fromDate = new \DateTime($data['from_date']);
                $toDate = new \DateTime($data['to_date']);
                $durationInDays = $toDate->diff($fromDate)->days + 1;
                return $durationInDays * 8;
            })(),
            remarks: $data['remarks'] ?? null,
            // احتساب شهر وسنة الإجازة من تاريخ البداية
            leaveMonth: (new \DateTime($data['from_date']))->format('m'),
            leaveYear: (new \DateTime($data['from_date']))->format('Y'),
            status: NumericalStatusEnum::PENDING->value,
            isDeducted: $data['is_deducted'] ?? false,
            place: $data['place'] ?? false,
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
            'is_half_day' => $this->isHalfDay,
            'leave_hours' => $this->leaveHours,
            'remarks' => $this->remarks,
            'leave_month' => $fromDate->format('m'),
            'leave_year' => $fromDate->format('Y'),
            'status' => NumericalStatusEnum::PENDING->value, // Pending by default
            'is_deducted' => $this->isDeducted,
            'place' => $this->place,
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
