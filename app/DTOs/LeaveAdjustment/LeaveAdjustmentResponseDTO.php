<?php

namespace App\DTOs\LeaveAdjustment;

use App\Models\LeaveAdjustment;

class LeaveAdjustmentResponseDTO
{
    public function __construct(
        public readonly int $adjustmentId,
        public readonly int $companyId,
        public readonly int $employeeId,
        public readonly string $employeeName,
        public readonly ?int $dutyEmployeeId,
        public readonly ?string $dutyEmployeeName,
        public readonly int $leaveTypeId,
        public readonly string $leaveTypeName,
        public readonly float $adjustHours,
        public readonly string $reasonAdjustment,
        public readonly int $status,
        public readonly string $statusText,
        public readonly ?string $adjustmentDate,
        public readonly string $createdAt,
        public readonly ?array $employee = null,
        public readonly ?array $approvals = null,
    ) {}

    public static function fromModel(LeaveAdjustment $adjustment): self
    {
        // Load relationships if not already loaded
        if (!$adjustment->relationLoaded('employee')) {
            $adjustment->load('employee');
        }
        if (!$adjustment->relationLoaded('approvals')) {
            $adjustment->load('approvals.staff');
        }
        if (!$adjustment->relationLoaded('leaveType')) {
            $adjustment->load('leaveType');
        }
        if (!$adjustment->relationLoaded('dutyEmployee')) {
            $adjustment->load('dutyEmployee');
        }

        $employee = $adjustment->employee ? [
            'user_id' => $adjustment->employee->user_id,
            'first_name' => $adjustment->employee->first_name,
            'last_name' => $adjustment->employee->last_name,
            'email' => $adjustment->employee->email,
            'full_name' => $adjustment->employee->full_name,
        ] : null;

        $approvals = $adjustment->approvals->map(function ($approval) {
            return [
                'staff_approval_id' => $approval->staff_approval_id,
                'staff_id' => $approval->staff_id,
                'staff_name' => $approval->staff ? $approval->staff->full_name : null,
                'status' => $approval->status,
                'approval_level' => $approval->approval_level,
                'updated_at' => $approval->updated_at,
            ];
        })->toArray();

        return new self(
            adjustmentId: $adjustment->adjustment_id,
            companyId: $adjustment->company_id,
            employeeId: $adjustment->employee_id,
            employeeName: $adjustment->employee ?
                ($adjustment->employee->first_name . ' ' . $adjustment->employee->last_name) : 'غير محدد',
            dutyEmployeeId: $adjustment->duty_employee_id,
            dutyEmployeeName: $adjustment->dutyEmployee ?
                ($adjustment->dutyEmployee->first_name . ' ' . $adjustment->dutyEmployee->last_name) : null,
            leaveTypeId: $adjustment->leave_type_id,
            leaveTypeName: $adjustment->leaveType ? $adjustment->leaveType->category_name : 'غير محدد',
            adjustHours: (float) $adjustment->adjust_hours,
            reasonAdjustment: $adjustment->reason_adjustment,
            status: $adjustment->status,
            statusText: self::getStatusText($adjustment->status),
            adjustmentDate: $adjustment->adjustment_date,
            createdAt: $adjustment->created_at,
            employee: $employee,
            approvals: $approvals,
        );
    }

    private static function getStatusText(int $status): string
    {
        return match ($status) {
            0 => 'قيد المراجعة',
            1 => 'موافق عليه',
            2 => 'مرفوض',
            default => 'غير معروف',
        };
    }

    public function toArray(): array
    {
        return [
            'adjustment_id' => $this->adjustmentId,
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'employee_name' => $this->employeeName,
            'duty_employee_id' => $this->dutyEmployeeId,
            'duty_employee_name' => $this->dutyEmployeeName,
            'leave_type_id' => $this->leaveTypeId,
            'leave_type_name' => $this->leaveTypeName,
            'adjust_hours' => $this->adjustHours,
            'reason_adjustment' => $this->reasonAdjustment,
            'status' => $this->status,
            'status_text' => $this->statusText,
            'adjustment_date' => $this->adjustmentDate,
            'created_at' => $this->createdAt,
            'employee' => $this->employee,
            'approvals' => $this->approvals,
        ];
    }
}
