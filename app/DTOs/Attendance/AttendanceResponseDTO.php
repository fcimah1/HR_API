<?php

namespace App\DTOs\Attendance;

use App\Models\Attendance;

class AttendanceResponseDTO
{
    public function __construct(
        public readonly int $timeAttendanceId,
        public readonly int $companyId,
        public readonly int $employeeId,
        public readonly string $attendanceDate,
        public readonly string $clockIn,
        public readonly ?string $clockOut,
        public readonly string $totalWork,
        public readonly ?string $totalRest,
        public readonly string $attendanceStatus,
        public readonly string $status,
        public readonly int $workFromHome,
        public readonly ?string $lunchBreakIn,
        public readonly ?string $lunchBreakOut,
        public readonly ?array $employee = null
    ) {}

    public static function fromModel(Attendance $attendance, bool $includeEmployee = false): self
    {
        return new self(
            timeAttendanceId: $attendance->time_attendance_id,
            companyId: $attendance->company_id,
            employeeId: $attendance->employee_id,
            attendanceDate: $attendance->attendance_date,
            clockIn: $attendance->clock_in,
            clockOut: $attendance->clock_out ?: null,
            totalWork: $attendance->total_work ?? '00:00',
            totalRest: $attendance->total_rest,
            attendanceStatus: $attendance->attendance_status,
            status: $attendance->status !== null
                ? ucfirst(strtolower(\App\Enums\AttendenceStatus::tryFrom((int)$attendance->status)?->name ?? (string)$attendance->status))
                : 'Pending',
            workFromHome: $attendance->work_from_home,
            lunchBreakIn: $attendance->lunch_breakin,
            lunchBreakOut: $attendance->lunch_breakout,
            employee: $includeEmployee && $attendance->employee ? [
                'user_id' => $attendance->employee->user_id,
                'first_name' => $attendance->employee->first_name,
                'last_name' => $attendance->employee->last_name,
                'email' => $attendance->employee->email,
            ] : null
        );
    }

    public function toArray(): array
    {
        $data = [
            'time_attendance_id' => $this->timeAttendanceId,
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'attendance_date' => $this->attendanceDate,
            'clock_in' => $this->clockIn,
            'clock_out' => $this->clockOut,
            'total_work' => $this->totalWork,
            'total_rest' => $this->totalRest,
            'attendance_status' => $this->attendanceStatus,
            'status' => $this->status,
            'work_from_home' => $this->workFromHome,
            'lunch_break_in' => $this->lunchBreakIn,
            'lunch_break_out' => $this->lunchBreakOut,
        ];

        if ($this->employee !== null) {
            $data['employee'] = $this->employee;
        }

        return $data;
    }
}
