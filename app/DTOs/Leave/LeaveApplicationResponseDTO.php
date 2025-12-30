<?php

namespace App\DTOs\Leave;

use App\Enums\DeductedStatus;
use App\Enums\LeavePlaceEnum;
use App\Enums\NumericalStatusEnum;
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
        public readonly string $leaveMonth,
        public readonly string $leaveYear,
        public readonly string $isDeducted,
        public readonly string $placeType,
        public readonly ?int $dutyEmployeeId,
        public readonly ?string $dutyEmployeeName,
        public readonly ?bool $isHalfDay,
        public readonly ?string $leaveHours,
        public readonly ?string $remarks,
        public readonly int $status,
        public readonly string $statusText,
        public readonly string $createdAt,
        public readonly ?array $employee = null,
        public readonly ?array $approvals = null,
    ) {}

    public static function fromModel(LeaveApplication $application): self
    {
        // Load relationships if not already loaded
        if (!$application->relationLoaded('employee')) {
            $application->load('employee');
        }
        if (!$application->relationLoaded('approvals')) {
            $application->load('approvals.staff');
        }

        $employee = $application->employee ? [
            'user_id' => $application->employee->user_id,
            'first_name' => $application->employee->first_name,
            'last_name' => $application->employee->last_name,
            'email' => $application->employee->email,
            'full_name' => $application->employee->full_name,
        ] : null;

        $approvals = $application->approvals->map(function ($approval) {
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
            leaveMonth: $application->leave_month,
            leaveYear: $application->leave_year,
            // تحويل قيم Enums إلى نصوص لتتوافق مع نوع البيانات المتوقعة (string)
            // عرض حالة الخصم كقيمة نصية عربية بدلاً من الرقم
            isDeducted: $application->is_deducted === 1
                ? DeductedStatus::DEDUCTED->labelAr()
                : DeductedStatus::NOT_DEDUCTED->labelAr(),
            // عرض مكان الإجازة كقيمة نصية عربية بدلاً من الرقم
            placeType: $application->place === 1
                ? LeavePlaceEnum::INSIDE->labelAr()
                : LeavePlaceEnum::OUTSIDE->labelAr(),
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
            createdAt: $application->created_at,
            employee: $employee,
            approvals: $approvals,
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
            case NumericalStatusEnum::PENDING->value:
                return 'pending';
            case NumericalStatusEnum::APPROVED->value:
                return 'approved';
            case NumericalStatusEnum::REJECTED->value:
                return 'rejected';
            default:
                return 'pending';
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
            'is_deducted' => $this->isDeducted,
            'place_type' => $this->placeType,
            'reason' => $this->reason,
            'duty_employee_id' => $this->dutyEmployeeId,
            'duty_employee_name' => $this->dutyEmployeeName,
            'is_half_day' => $this->isHalfDay,
            'leave_hours' => $this->leaveHours,
            'remarks' => $this->remarks,
            'is_deducted_text' => $this->isDeducted === DeductedStatus::DEDUCTED->value ? DeductedStatus::DEDUCTED->labelAr() : DeductedStatus::NOT_DEDUCTED->labelAr(),
            'place_text' => $this->placeType === LeavePlaceEnum::INSIDE->value ? LeavePlaceEnum::INSIDE->labelAr() : LeavePlaceEnum::OUTSIDE->labelAr(),
            'status' => $this->status,
            'status_text' => $this->statusText,
            'created_at' => $this->createdAt,
            'employee' => $this->employee,
            'approvals' => $this->approvals,
        ];
    }
}
