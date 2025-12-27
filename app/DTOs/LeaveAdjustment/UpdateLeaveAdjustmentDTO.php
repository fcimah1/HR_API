<?php

namespace App\DTOs\LeaveAdjustment;

class UpdateLeaveAdjustmentDTO
{
    public function __construct(
        public readonly ?int $leaveTypeId = null,
        public readonly ?string $adjustHours = null,
        public readonly ?string $reasonAdjustment = null,
        public readonly ?string $adjustmentDate = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            leaveTypeId: $data['leave_type_id'] ?? null,
            adjustHours: $data['adjust_hours'] ?? null,
            reasonAdjustment: $data['reason_adjustment'] ?? null,
            adjustmentDate: $data['adjustment_date'] ?? null,
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


        return $data;
    }

    public function hasUpdates(): bool
    {
        return !empty($this->toArray());
    }
}
