<?php

namespace App\Repository;

use App\Repository\Interface\AttendanceRepositoryInterface;
use App\DTOs\Attendance\AttendanceFilterDTO;
use App\DTOs\Attendance\CreateAttendanceDTO;
use App\DTOs\Attendance\UpdateAttendanceDTO;
use App\Models\Attendance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class AttendanceRepository implements AttendanceRepositoryInterface
{
    /**
     * Get paginated attendance records with filters
     */
    public function getPaginatedRecords(AttendanceFilterDTO $filters): LengthAwarePaginator
    {
        $query = Attendance::with(['employee']);

        if ($filters->search !== null && trim($filters->search) !== '') {
            $searchTerm = '%' . $filters->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                // البحث في بيانات الموظف
                $q->whereHas('employee', function ($subQuery) use ($searchTerm) {
                    $subQuery->where('first_name', 'like', $searchTerm)
                        ->orWhere('last_name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm);
                });
            });
        }
        // Apply company filter
        if ($filters->companyId !== null) {
            $query->where('company_id', $filters->companyId);
        }

        // Apply employee filter
        if ($filters->employeeId !== null) {
            $query->where('employee_id', $filters->employeeId);
        }

        // Apply date range filter
        if ($filters->fromDate !== null && $filters->toDate !== null) {
            $query->whereBetween('attendance_date', [$filters->fromDate, $filters->toDate]);
        } elseif ($filters->fromDate !== null) {
            $query->where('attendance_date', '>=', $filters->fromDate);
        } elseif ($filters->toDate !== null) {
            $query->where('attendance_date', '<=', $filters->toDate);
        }

        // Apply status filter
        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        // Apply work from home filter
        if ($filters->workFromHome !== null) {
            $query->where('work_from_home', $filters->workFromHome);
        }

        // Order by date and clock_in descending
        $query->orderBy('attendance_date', 'desc')
            ->orderBy('clock_in', 'desc');

        return $query->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }

    /**
     * Clock in - create new attendance record
     */
    public function clockIn(CreateAttendanceDTO $dto): Attendance
    {
        $attendance = Attendance::create($dto->toArray());
        $attendance->load(['employee']);

        Log::info('Clock in successful', [
            'attendance_id' => $attendance->time_attendance_id,
            'employee_id' => $attendance->employee_id,
            'attendance_date' => $attendance->attendance_date,
        ]);

        return $attendance;
    }

    /**
     * Clock out - update attendance record
     */
    public function clockOut(Attendance $attendance, UpdateAttendanceDTO $dto): Attendance
    {
        $attendance->update($dto->toArray());
        $attendance->refresh();
        $attendance->load(['employee']);

        Log::info('Clock out successful', [
            'attendance_id' => $attendance->time_attendance_id,
            'employee_id' => $attendance->employee_id,
            'total_work' => $attendance->total_work,
        ]);

        return $attendance;
    }

    /**
     * Start lunch break
     */
    public function lunchBreakIn(Attendance $attendance, UpdateAttendanceDTO $dto): Attendance
    {
        $attendance->update($dto->toArray());
        $attendance->refresh();

        Log::info('Lunch break started', [
            'attendance_id' => $attendance->time_attendance_id,
            'employee_id' => $attendance->employee_id,
        ]);

        return $attendance;
    }

    /**
     * End lunch break
     */
    public function lunchBreakOut(Attendance $attendance, UpdateAttendanceDTO $dto): Attendance
    {
        $attendance->update($dto->toArray());
        $attendance->refresh();

        Log::info('Lunch break ended', [
            'attendance_id' => $attendance->time_attendance_id,
            'employee_id' => $attendance->employee_id,
        ]);

        return $attendance;
    }

    /**
     * Find attendance record by ID
     */
    public function findAttendance(int $id): ?Attendance
    {
        return Attendance::with(['employee'])->find($id);
    }

    /**
     * Find today's attendance for an employee
     */
    public function findTodayAttendance(string|int $employeeId, ?string $date = null): ?Attendance
    {
        $date = $date ?? now()->format('Y-m-d');

        return Attendance::where('employee_id', $employeeId)
            ->where('attendance_date', $date)
            ->first();
    }

    /**
     * Find attendance in company
     */
    public function findAttendanceInCompany(int $id, int $companyId): ?Attendance
    {
        return Attendance::with(['employee'])
            ->where('time_attendance_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * Update attendance record
     */
    public function updateAttendance(Attendance $attendance, UpdateAttendanceDTO $dto): Attendance
    {
        $attendance->update($dto->toArray());
        $attendance->refresh();
        $attendance->load(['employee']);

        Log::info('Attendance updated', [
            'attendance_id' => $attendance->time_attendance_id,
        ]);

        return $attendance;
    }

    /**
     * Delete attendance record
     */
    public function deleteAttendance(int $id): bool
    {
        $attendance = Attendance::find($id);

        if (!$attendance) {
            return false;
        }

        Log::info('Attendance deleted', [
            'attendance_id' => $id,
            'employee_id' => $attendance->employee_id,
        ]);

        return $attendance->delete();
    }

    /**
     * Get monthly attendance report for an employee
     */
    public function getMonthlyReport(int $employeeId, string $month, int $companyId): array
    {
        // Parse month (format: YYYY-MM)
        $year = substr($month, 0, 4);
        $monthNum = substr($month, 5, 2);

        // Get first and last day of the month
        $firstDay = $year . '-' . $monthNum . '-01';
        $lastDay = date('Y-m-t', strtotime($firstDay));

        $records = Attendance::where('employee_id', $employeeId)
            ->where('company_id', $companyId)
            ->whereBetween('attendance_date', [$firstDay, $lastDay])
            ->orderBy('attendance_date', 'asc')
            ->get();

        $totalDaysPresent = 0;
        $totalWorkHours = 0.0;
        $totalLateMinutes = 0;
        $totalEarlyLeaving = 0;

        foreach ($records as $record) {
            if ($record->attendance_status === 'Present') {
                $totalDaysPresent++;
            }

            // Parse total_work (format: HH:MM)
            if ($record->total_work) {
                list($hours, $minutes) = explode(':', $record->total_work);
                $totalWorkHours += (int)$hours + ((int)$minutes / 60);
            }
        }

        return [
            'month' => $month,
            'employee_id' => $employeeId,
            'total_days_present' => $totalDaysPresent,
            'total_work_hours' => round($totalWorkHours, 2),
            'total_late_minutes' => $totalLateMinutes,
            'total_early_leaving' => $totalEarlyLeaving,
            'records' => $records->toArray(),
        ];
    }

    /**
     * Check if employee has clocked in today
     */
    public function hasClockedInToday(string|int $employeeId, ?string $date = null): bool
    {
        return $this->findTodayAttendance($employeeId, $date) !== null;
    }

    /**
     * Get attendance statistics for company
     */
    public function getAttendanceStatistics(int $companyId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = Attendance::where('company_id', $companyId);

        if ($fromDate && $toDate) {
            $query->whereBetween('attendance_date', [$fromDate, $toDate]);
        }

        $totalRecords = $query->count();
        $presentRecords = $query->where('attendance_status', 'Present')->count();
        $absentRecords = $query->where('attendance_status', 'Absent')->count();
        $workFromHomeRecords = $query->where('work_from_home', 1)->count();

        return [
            'total_records' => $totalRecords,
            'present' => $presentRecords,
            'absent' => $absentRecords,
            'work_from_home' => $workFromHomeRecords,
        ];
    }
}
