<?php

namespace App\DTOs\Leave;

class UpdateLeaveApplicationDTO
{
    public function __construct(
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly ?string $reason = null,
        public readonly ?int $dutyEmployeeId = null,
        public readonly ?bool $isHalfDay = null,
        public readonly ?string $leaveHours = null,
        public readonly ?string $remarks = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            fromDate: $data['from_date'] ?? null,
            toDate: $data['to_date'] ?? null,
            reason: $data['reason'] ?? null,
            dutyEmployeeId: $data['duty_employee_id'] ?? null,
            isHalfDay: isset($data['is_half_day']) ? (bool) $data['is_half_day'] : null,
            leaveHours: $data['leave_hours'] ?? null,
            remarks: $data['remarks'] ?? null
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->fromDate !== null) {
            $data['from_date'] = $this->fromDate;
            $fromDate = new \DateTime($this->fromDate);
            $data['leave_month'] = $fromDate->format('m');
            $data['leave_year'] = $fromDate->format('Y');
        }

        if ($this->toDate !== null) {
            $data['to_date'] = $this->toDate;
        }

        if ($this->reason !== null) {
            $data['reason'] = $this->reason;
        }

        if ($this->dutyEmployeeId !== null) {
            $data['duty_employee_id'] = $this->dutyEmployeeId;
        }

        if ($this->isHalfDay !== null) {
            $data['is_half_day'] = $this->isHalfDay;
        }

        if ($this->leaveHours !== null) {
            $data['leave_hours'] = $this->leaveHours;
        }

        if ($this->remarks !== null) {
            $data['remarks'] = $this->remarks;
        }

        return $data;
    }

    public function hasUpdates(): bool
    {
        return !empty($this->toArray());
    }
}
