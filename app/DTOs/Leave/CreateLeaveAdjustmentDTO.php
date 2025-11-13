<?php

namespace App\DTOs\Leave;

class CreateLeaveAdjustmentDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $employeeId,
        public readonly int $leaveTypeId,
        public readonly string $adjustHours,
        public readonly string $reasonAdjustment,
        public readonly ?string $adjustmentDate = null,
        public readonly ?int $dutyEmployeeId = null
    ) {}

    public static function fromRequest(array $data, int $companyId, int $employeeId): self
    {
        return new self(
            companyId: $companyId,
            employeeId: $employeeId,
            leaveTypeId: $data['leave_type_id'],
            adjustHours: $data['adjust_hours'],
            reasonAdjustment: $data['reason_adjustment'],
            adjustmentDate: $data['adjustment_date'] ?? null,
            dutyEmployeeId: $data['duty_employee_id'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'leave_type_id' => $this->leaveTypeId,
            'adjust_hours' => $this->adjustHours,
            'reason_adjustment' => $this->reasonAdjustment,
            'adjustment_date' => $this->adjustmentDate,
            'duty_employee_id' => $this->dutyEmployeeId,
            'status' => \App\Models\LeaveAdjustment::STATUS_PENDING,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
