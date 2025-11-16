<?php

namespace App\DTOs\Leave;

use Illuminate\Support\Facades\Log;

class CreateLeaveAdjustmentDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $employeeId,
        public readonly int $leaveTypeId,
        public readonly float $adjustHours,  // تغيير من string إلى float
        public readonly string $reasonAdjustment,
        public readonly string $adjustmentDate, // إزالة القيمة الافتراضية
        public readonly ?int $dutyEmployeeId = null
    ) {}


    public static function fromRequest(array $data, int $companyId, int $employeeId): self
    {

        return new self(
            companyId: $companyId,
            employeeId: $employeeId,
            leaveTypeId: (int)$data['leave_type_id'],
            adjustHours: (float)$data['adjust_hours'],
            reasonAdjustment: $data['reason_adjustment'],
            adjustmentDate: $data['adjustment_date'],
            dutyEmployeeId: $data['duty_employee_id'] ?? null
        );
    }
    public function toArray(): array
    {
        $data = [
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'leave_type_id' => $this->leaveTypeId,
            'adjust_hours' => (float)$this->adjustHours,
            'reason_adjustment' => $this->reasonAdjustment,
            'adjustment_date' => $this->adjustmentDate,
            'duty_employee_id' => $this->dutyEmployeeId,
            'status' => \App\Models\LeaveAdjustment::STATUS_PENDING,
            'created_at' => now(),
        ];

        return $data;
    }
}
