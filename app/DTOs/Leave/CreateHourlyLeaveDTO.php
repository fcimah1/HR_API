<?php

namespace App\DTOs\Leave;

use App\Enums\NumericalStatusEnum;
use Spatie\LaravelData\Data;

class CreateHourlyLeaveDTO extends Data
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $employeeId,
        public readonly int $leaveTypeId,
        public readonly ?int $dutyEmployeeId = null,
        public readonly string $date,
        public readonly string $clockInM,
        public readonly string $clockOutM,
        public readonly string $reason,
        public readonly ?string $remarks = null,
        public readonly ?int $status = null,
        public readonly ?float $leaveHours = 0,
    ) {}

    public static function fromRequest(array $data, int $companyId, int $employeeId): self
    {
        // Calculate leave hours from time strings
        $startTime = \Carbon\Carbon::parse($data['date'] . ' ' . $data['clock_in_m']);
        $endTime = \Carbon\Carbon::parse($data['date'] . ' ' . $data['clock_out_m']);
        $leaveHours = $startTime->diffInMinutes($endTime) / 60;

        return new self(
            companyId: $companyId,
            employeeId: $employeeId,
            leaveTypeId: $data['leave_type_id'],
            dutyEmployeeId: $data['duty_employee_id'] ?? null,
            date: $data['date'],
            clockInM: $data['clock_in_m'],
            clockOutM: $data['clock_out_m'],
            reason: $data['reason'],
            remarks: $data['remarks'] ?? null,
            status: NumericalStatusEnum::PENDING->value,
            leaveHours: $leaveHours,
        );
    }

    public function toArray(): array
    {
        $fromDate = new \DateTime($this->date);

        return [
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'leave_type_id' => $this->leaveTypeId,
            'duty_employee_id' => $this->dutyEmployeeId,
            'from_date' => $this->date,
            'to_date' => $this->date,
            'particular_date' => $this->date,
            'reason' => $this->reason,
            'leave_hours' => $this->leaveHours,
            'remarks' => $this->remarks,
            'leave_month' => $fromDate->format('m'),
            'leave_year' => $fromDate->format('Y'),
            'status' => NumericalStatusEnum::PENDING->value, // Pending by default
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
