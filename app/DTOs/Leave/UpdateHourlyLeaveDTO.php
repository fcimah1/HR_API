<?php

namespace App\DTOs\Leave;

class UpdateHourlyLeaveDTO
{
    public function __construct(
        public readonly ?string $date          = null,
        public readonly ?string $clockInM      = null,
        public readonly ?string $clockOutM     = null,
        public readonly ?string $reason        = null,
        public readonly ?int    $dutyEmployeeId = null,
        public readonly ?string $remarks       = null,
        // حقول Leave module
        public readonly ?string $countryCode   = null,
        public readonly ?float  $serviceYears  = null,
        public readonly ?int    $policyId      = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            date: $data['date']             ?? null,
            clockInM: $data['clock_in_m']       ?? null,
            clockOutM: $data['clock_out_m']      ?? null,
            reason: $data['reason']           ?? null,
            dutyEmployeeId: $data['duty_employee_id'] ?? null,
            remarks: $data['remarks']          ?? null,
            countryCode: $data['country_code']     ?? null,
            serviceYears: isset($data['service_years']) ? (float) $data['service_years'] : null,
            policyId: isset($data['policy_id'])     ? (int)   $data['policy_id']     : null,
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->date !== null) {
            $data['from_date'] = $this->clockInM;
            $data['to_date'] = $this->clockOutM;
            $data['particular_date'] = $this->date;
            $fromDate = new \DateTime($this->date);
            $data['leave_month'] = $fromDate->format('m');
            $data['leave_year'] = $fromDate->format('Y');
        }

        if ($this->clockInM !== null && $this->clockOutM !== null && $this->date !== null) {
            // حساب ساعات الإجازة
            $startTime = \Carbon\Carbon::parse($this->date . ' ' . $this->clockInM);
            $endTime = \Carbon\Carbon::parse($this->date . ' ' . $this->clockOutM);

            // التحقق من أن وقت النهاية بعد وقت البداية
            if ($endTime <= $startTime) {
                $leaveHours = 0; // أو يمكن إرجاع قيمة سالبة للإشارة للخطأ
            } else {
                $leaveHours = abs($startTime->diffInMinutes($endTime)) / 60;
            }

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

        if ($this->countryCode !== null) {
            $data['country_code'] = $this->countryCode;
        }

        if ($this->serviceYears !== null) {
            $data['service_years'] = $this->serviceYears;
        }

        if ($this->policyId !== null) {
            $data['policy_id'] = $this->policyId;
        }

        // calculated_days دائماً 0 للإستئذان بالساعات
        $data['calculated_days'] = 0;

        return $data;
    }

    public function hasUpdates(): bool
    {
        return !empty($this->toArray());
    }
}
