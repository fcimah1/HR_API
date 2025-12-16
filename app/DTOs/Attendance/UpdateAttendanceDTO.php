<?php

namespace App\DTOs\Attendance;

class UpdateAttendanceDTO
{
    public function __construct(
        public readonly ?string $clockOut = null,
        public readonly ?string $clockOutIpAddress = null,
        public readonly ?string $clockOutLatitude = null,
        public readonly ?string $clockOutLongitude = null,
        public readonly ?string $totalWork = null,
        public readonly ?string $status = null,
        public readonly ?string $lunchBreakIn = null,
        public readonly ?string $lunchBreakOut = null,
        public readonly ?string $earlyLeaving = null,
        public readonly ?string $overtime = null
    ) {}

    public static function fromClockOutRequest(array $data, string $ipAddress, string $totalWork, ?string $earlyLeaving = null, ?string $overtime = null): self
    {
        $now = now();

        return new self(
            clockOut: $now->format('Y-m-d H:i:s'),
            clockOutIpAddress: $ipAddress,
            clockOutLatitude: $data['latitude'] ?? null,
            clockOutLongitude: $data['longitude'] ?? null,
            totalWork: $totalWork,
            status: null,
            lunchBreakIn: null,
            lunchBreakOut: null,
            earlyLeaving: $earlyLeaving,
            overtime: $overtime
        );
    }

    public static function forLunchBreakIn(): self
    {
        return new self(
            lunchBreakIn: now()->format('Y-m-d H:i:s'),
            lunchBreakOut: null
        );
    }

    public static function forLunchBreakOut(): self
    {
        return new self(
            lunchBreakOut: now()->format('Y-m-d H:i:s')
        );
    }

    public static function fromUpdateRequest(array $data): self
    {
        return new self(
            clockOut: $data['clock_out'] ?? null,
            clockOutIpAddress: $data['clock_out_ip_address'] ?? null,
            clockOutLatitude: $data['clock_out_latitude'] ?? null,
            clockOutLongitude: $data['clock_out_longitude'] ?? null,
            totalWork: $data['total_work'] ?? null,
            status: $data['status'] ?? null,
            earlyLeaving: $data['early_leaving'] ?? null,
            overtime: $data['overtime'] ?? null
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->clockOut !== null) {
            $data['clock_out'] = $this->clockOut;
            $data['clock_in_out'] = '0';
        }

        if ($this->clockOutIpAddress !== null) {
            $data['clock_out_ip_address'] = $this->clockOutIpAddress;
        }

        if ($this->clockOutLatitude !== null) {
            $data['clock_out_latitude'] = $this->clockOutLatitude;
        }

        if ($this->clockOutLongitude !== null) {
            $data['clock_out_longitude'] = $this->clockOutLongitude;
        }

        if ($this->totalWork !== null) {
            $data['total_work'] = $this->totalWork;
        }

        if ($this->status !== null) {
            $data['status'] = $this->status;
        }

        if ($this->lunchBreakIn !== null) {
            $data['lunch_breakin'] = $this->lunchBreakIn;
            $data['lunch_breakout'] = '0';
        }

        if ($this->lunchBreakOut !== null) {
            $data['lunch_breakout'] = $this->lunchBreakOut;
        }

        if ($this->earlyLeaving !== null) {
            $data['early_leaving'] = $this->earlyLeaving;
        }

        if ($this->overtime !== null) {
            $data['overtime'] = $this->overtime;
        }

        return $data;
    }
}
