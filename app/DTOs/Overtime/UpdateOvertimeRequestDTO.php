<?php

namespace App\DTOs\Overtime;

class UpdateOvertimeRequestDTO
{
    public function __construct(
        public readonly string $requestDate,
        public readonly string $clockIn,
        public readonly string $clockOut,
        public readonly int $overtimeReason,
        public readonly float $additionalWorkHours,
        public readonly int $compensationType,
        public readonly ?string $requestReason = null,
        public readonly ?string $straight = null,
        public readonly ?string $timeAHalf = null,
        public readonly ?string $doubleOvertime = null,
        public readonly ?string $totalHours = null,
        public readonly ?string $compensationBanked = null,
    ) {}

    /**
     * Create DTO from request array with enum string conversion.
     */
    public static function fromRequest(array $data): self
    {
        // Convert enum strings to integers for business logic
        $overtimeReasonInt = is_string($data['overtime_reason']) 
            ? constant(\App\Enums\OvertimeReasonEnum::class . '::' . $data['overtime_reason'])->value
            : $data['overtime_reason'];
            
        $compensationTypeInt = is_string($data['compensation_type']) 
            ? constant(\App\Enums\CompensationTypeEnum::class . '::' . $data['compensation_type'])->value
            : $data['compensation_type'];
        
        return new self(
            requestDate: $data['request_date'],
            clockIn: $data['clock_in'],
            clockOut: $data['clock_out'],
            overtimeReason: $overtimeReasonInt,
            additionalWorkHours: (float)($data['additional_work_hours'] ?? 0),
            compensationType: $compensationTypeInt,
            requestReason: $data['request_reason'] ?? null,
            straight: $data['straight'] ?? null,
            timeAHalf: $data['time_a_half'] ?? null,
            doubleOvertime: $data['double_overtime'] ?? null,
            totalHours: $data['total_hours'] ?? null,
            compensationBanked: $data['compensation_banked'] ?? null,
        );
    }

    /**
     * Convert DTO to array for database update.
     */
    public function toArray(): array
    {
        return [
            'request_date' => $this->requestDate,
            'request_month' => date('Y-m', strtotime($this->requestDate)),
            'clock_in' => $this->clockIn,
            'clock_out' => $this->clockOut,
            'overtime_reason' => $this->overtimeReason,
            'additional_work_hours' => $this->additionalWorkHours,
            'compensation_type' => $this->compensationType,
            'request_reason' => $this->requestReason,
            'straight' => $this->straight,
            'time_a_half' => $this->timeAHalf,
            'double_overtime' => $this->doubleOvertime,
            'total_hours' => $this->totalHours,
            'compensation_banked' => $this->compensationBanked,
        ];
    }
}

