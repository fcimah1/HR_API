<?php

namespace App\DTOs\Leave;

use App\Models\LeaveApplication;

class LeaveApplicationResponseDTO
{
    public function __construct(
        public readonly int $leaveId,
        public readonly int $companyId,
        public readonly int $employeeId,
        public readonly string $employeeName,
        public readonly int $leaveTypeId,
        public readonly string $leaveTypeName,
        public readonly string $fromDate,
        public readonly string $toDate,
        public readonly int $durationDays,
        public readonly string $reason,
        public readonly ?int $dutyEmployeeId,
        public readonly ?string $dutyEmployeeName,
        public readonly ?bool $isHalfDay,
        public readonly ?string $leaveHours,
        public readonly ?string $remarks,
        public readonly int $status,
        public readonly string $statusText,
        public readonly string $createdAt
    ) {}

    public static function fromModel(LeaveApplication $application): self
    {
        return new self(
            leaveId: $application->leave_id,
            companyId: $application->company_id,
            employeeId: $application->employee_id,
            employeeName: $application->employee ?
                ($application->employee->first_name . ' ' . $application->employee->last_name) : 'غير محدد',
            leaveTypeId: $application->leave_type_id,
            leaveTypeName: $application->leaveType ?
                $application->leaveType->category_name : 'غير محدد',
            fromDate: $application->from_date,
            toDate: $application->to_date,
            durationDays: self::calculateDuration($application->from_date, $application->to_date),
            reason: $application->reason,
            dutyEmployeeId: $application->duty_employee_id,
            dutyEmployeeName: $application->dutyEmployee ?
                ($application->dutyEmployee->first_name . ' ' . $application->dutyEmployee->last_name) : null,
            isHalfDay: $application->is_half_day,
            leaveHours: $application->leave_hours,
            remarks: $application->remarks,
            status: $application->status,
            statusText: self::getStatusText($application->status),
            createdAt: $application->created_at
        );
    }

    private static function calculateDuration(?string $fromDate, ?string $toDate): int
    {
        if (!$fromDate || !$toDate) {
            return 0;
        }

        try {
            $from = new \DateTime($fromDate);
            $to = new \DateTime($toDate);
            $diff = $to->diff($from);
            return $diff->days + 1; // +1 لتضمين يوم البداية
        } catch (\Exception $e) {
            return 0;
        }
    }

    private static function getStatusText($status): string
    {
        switch ($status) {
            case 0:
                return 'قيد المراجعة';
            case 1:
                return 'موافق عليه';
            case 2:
                return 'مرفوض';
            case 3:
                return 'ملغي';
            default:
                return 'غير محدد';
        }
    }

    public function toArray(): array
    {
        return [
            'leave_id' => $this->leaveId,
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'employee_name' => $this->employeeName,
            'leave_type_id' => $this->leaveTypeId,
            'leave_type_name' => $this->leaveTypeName,
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
            'duration_days' => $this->durationDays,
            'reason' => $this->reason,
            'duty_employee_id' => $this->dutyEmployeeId,
            'duty_employee_name' => $this->dutyEmployeeName,
            'is_half_day' => $this->isHalfDay,
            'leave_hours' => $this->leaveHours,
            'remarks' => $this->remarks,
            'status' => $this->status,
            'status_text' => $this->statusText,
            'created_at' => $this->createdAt,
        ];
    }
}
