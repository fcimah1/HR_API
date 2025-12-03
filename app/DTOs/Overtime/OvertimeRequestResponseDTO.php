<?php

namespace App\DTOs\Overtime;

use App\Models\OvertimeRequest;

class OvertimeRequestResponseDTO
{
    public function __construct(
        public readonly int $timeRequestId,
        public readonly int $companyId,
        public readonly int $staffId,
        public readonly string $requestDate,
        public readonly string $requestMonth,
        public readonly string $clockIn,
        public readonly string $clockOut,
        public readonly string $totalHours,
        public readonly ?string $requestReason,
        public readonly int $isApproved,
        public readonly string $statusText,
        public readonly int $overtimeReason,
        public readonly string $overtimeReasonText,
        public readonly int $additionalWorkHours,
        public readonly ?string $straight,
        public readonly ?string $timeAHalf,
        public readonly ?string $doubleOvertime,
        public readonly int $compensationType,
        public readonly string $compensationTypeText,
        public readonly ?string $compensationBanked,
        public readonly string $createdAt,
        public readonly ?array $employee = null,
        public readonly ?array $approvals = null,
    ) {}

    /**
     * Create DTO from OvertimeRequest model.
     */
    public static function fromModel(OvertimeRequest $request): self
    {
        // Load relationships if not already loaded
        if (!$request->relationLoaded('employee')) {
            $request->load('employee');
        }
        if (!$request->relationLoaded('approvals')) {
            $request->load('approvals.staff');
        }

        $employee = $request->employee ? [
            'user_id' => $request->employee->user_id,
            'first_name' => $request->employee->first_name,
            'last_name' => $request->employee->last_name,
            'email' => $request->employee->email,
            'full_name' => $request->employee->full_name,
        ] : null;

        $approvals = $request->approvals->map(function ($approval) {
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
            timeRequestId: $request->time_request_id,
            companyId: $request->company_id,
            staffId: $request->staff_id,
            requestDate: $request->request_date,
            requestMonth: $request->request_month,
            clockIn: $request->clock_in,
            clockOut: $request->clock_out,
            totalHours: $request->total_hours,
            requestReason: $request->request_reason,
            isApproved: $request->is_approved,
            statusText: $request->status_text,
            overtimeReason: $request->overtime_reason->value, // Extract integer from enum
            overtimeReasonText: $request->overtime_reason_text,
            additionalWorkHours: $request->additional_work_hours,
            straight: $request->straight,
            timeAHalf: $request->time_a_half,
            doubleOvertime: $request->double_overtime,
            compensationType: $request->compensation_type->value, // Extract integer from enum
            compensationTypeText: $request->compensation_type_text,
            compensationBanked: $request->compensation_banked,
            createdAt: $request->created_at,
            employee: $employee,
            approvals: $approvals,
        );
    }

    /**
     * Convert DTO to array.
     */
    public function toArray(): array
    {
        return [
            'time_request_id' => $this->timeRequestId,
            'company_id' => $this->companyId,
            'staff_id' => $this->staffId,
            'request_date' => $this->requestDate,
            'request_month' => $this->requestMonth,
            'clock_in' => $this->clockIn,
            'clock_out' => $this->clockOut,
            'total_hours' => $this->totalHours,
            'request_reason' => $this->requestReason,
            'is_approved' => $this->isApproved,
            'status_text' => $this->statusText,
            'overtime_reason' => $this->overtimeReason,
            'overtime_reason_text' => $this->overtimeReasonText,
            'additional_work_hours' => $this->additionalWorkHours,
            'straight' => $this->straight,
            'time_a_half' => $this->timeAHalf,
            'double_overtime' => $this->doubleOvertime,
            'compensation_type' => $this->compensationType,
            'compensation_type_text' => $this->compensationTypeText,
            'compensation_banked' => $this->compensationBanked,
            'created_at' => $this->createdAt,
            'employee' => $this->employee,
            'approvals' => $this->approvals,
        ];
    }
}

