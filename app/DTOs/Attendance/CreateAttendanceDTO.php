<?php

namespace App\DTOs\Attendance;

class CreateAttendanceDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $employeeId,
        public readonly string $attendanceDate,
        public readonly ?string $clockIn, // nullable for clock-out without clock-in
        public readonly ?string $clockInIpAddress, // nullable for clock-out without clock-in
        public readonly ?string $clockInLatitude = null,
        public readonly ?string $clockInLongitude = null,
        public readonly int $shiftId = 0,
        public readonly int $workFromHome = 0,
        public readonly string $attendanceStatus = 'Present',
        public readonly string $status = 'Approved',
        public readonly int $branchId = 0,
        public readonly ?string $timeLate = '00:00' // nullable for clock-out without clock-in
    ) {}

    public static function fromRequest(array $data, int $companyId, int $employeeId, string $ipAddress): self
    {
        $now = now();

        return new self(
            companyId: $companyId,
            employeeId: $employeeId,
            attendanceDate: $now->format('Y-m-d'),
            clockIn: $now->format('Y-m-d H:i:s'),
            clockInIpAddress: $ipAddress,
            clockInLatitude: $data['latitude'] ?? null,
            clockInLongitude: $data['longitude'] ?? null,
            shiftId: $data['shift_id'] ?? 0,
            workFromHome: $data['work_from_home'] ?? 0,
            attendanceStatus: 'Present',
            status: $data['status'] ?? 'Approved',
            branchId: $data['branch_id'] ?? 0,
            timeLate: $data['time_late'] ?? '00:00'
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'branch_id' => $this->branchId,
            'employee_id' => $this->employeeId,
            'attendance_date' => $this->attendanceDate,
            'clock_in' => $this->clockIn,
            'clock_in_ip_address' => $this->clockInIpAddress,
            'clock_out' => '',
            'clock_out_ip_address' => '',
            'clock_in_out' => '0',
            'clock_in_latitude' => $this->clockInLatitude ?? '',
            'clock_in_longitude' => $this->clockInLongitude ?? '',
            'clock_out_latitude' => '',
            'clock_out_longitude' => '',
            'time_late' => $this->timeLate,
            'early_leaving' => '00:00',
            'overtime' => '00:00',
            'total_work' => '00:00',
            'total_rest' => '',
            'shift_id' => $this->shiftId,
            'work_from_home' => $this->workFromHome,
            'lunch_breakin' => null,
            'lunch_breakout' => null,
            'attendance_status' => $this->attendanceStatus,
            'status' => $this->status,
        ];
    }
}
