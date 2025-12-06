<?php

namespace App\DTOs\Leave;

class UpdateHourlyLeaveDTO
{
    public function __construct(
        public readonly ?string $date = null,
        public readonly ?string $clockInM = null,
        public readonly ?string $clockOutM = null,
        public readonly ?string $reason = null,
        public readonly ?int $dutyEmployeeId = null,
        public readonly ?string $remarks = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            date: $data['date'] ?? null,
            clockInM: $data['clock_in_m'] ?? null,
            clockOutM: $data['clock_out_m'] ?? null,
            reason: $data['reason'] ?? null,
            dutyEmployeeId: $data['duty_employee_id'] ?? null,
            remarks: $data['remarks'] ?? null
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->date !== null) {
            $data['from_date'] = $this->date;
            $data['to_date'] = $this->date;
            $data['particular_date'] = $this->date;
            $fromDate = new \DateTime($this->date);
            $data['leave_month'] = $fromDate->format('m');
            $data['leave_year'] = $fromDate->format('Y');
        }

        if ($this->clockInM !== null && $this->clockOutM !== null && $this->date !== null) {
            // حساب ساعات الإجازة
            $startTime = \Carbon\Carbon::parse($this->date . ' ' . $this->clockInM);
            $endTime = \Carbon\Carbon::parse($this->date . ' ' . $this->clockOutM);
            $leaveHours = $endTime->diffInHours($startTime);
            $data['leave_hours'] = $leaveHours;
        }

        if ($this->reason !== null) {
            $data['reason'] = $this->reason;
        }

        if ($this->dutyEmployeeId !== null) {
            $data['duty_employee_id'] = $this->dutyEmployeeId;
        }

        if ($this->remarks !== null) {
            $data['remarks'] = $this->remarks;
        }

        return $data;
    }

    public function hasUpdates(): bool
    {
        return !empty($this->toArray());
    }
}

