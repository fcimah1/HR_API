<?php

namespace App\DTOs\LeaveAdjustment;

class UpdateLeaveAdjustmentDTO
{
    public function __construct(
        public readonly ?int $leaveTypeId = null,
        public readonly ?string $adjustHours = null,
        public readonly ?string $reasonAdjustment = null,
        public readonly ?string $adjustmentDate = null,
        public readonly ?int $dutyEmployeeId = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            leaveTypeId: $data['leave_type_id'] ?? null,
            adjustHours: $data['adjust_hours'] ?? null,
            reasonAdjustment: $data['reason_adjustment'] ?? null,
            adjustmentDate: $data['adjustment_date'] ?? null,
            dutyEmployeeId: $data['duty_employee_id'] ?? null
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->leaveTypeId !== null) {
            $data['leave_type_id'] = $this->leaveTypeId;
        }

        if ($this->adjustHours !== null) {
            $data['adjust_hours'] = $this->adjustHours;
        }

        if ($this->reasonAdjustment !== null) {
            $data['reason_adjustment'] = $this->reasonAdjustment;
        }

        if ($this->adjustmentDate !== null) {
            $data['adjustment_date'] = $this->adjustmentDate;
        }

        if ($this->dutyEmployeeId !== null) {
            $data['duty_employee_id'] = $this->dutyEmployeeId;
        }

        return $data;
    }

    public function hasUpdates(): bool
    {
        return !empty($this->toArray());
    }
}
