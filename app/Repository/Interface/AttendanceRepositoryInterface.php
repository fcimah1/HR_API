<?php

namespace App\Repository\Interface;

use App\DTOs\Attendance\AttendanceFilterDTO;
use App\DTOs\Attendance\CreateAttendanceDTO;
use App\DTOs\Attendance\UpdateAttendanceDTO;
use App\Models\Attendance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AttendanceRepositoryInterface
{
    /**
     * Get paginated attendance records with filters
     */
    public function getPaginatedRecords(AttendanceFilterDTO $filters): LengthAwarePaginator;

    /**
     * Clock in - create new attendance record
     */
    public function clockIn(CreateAttendanceDTO $dto): Attendance;

    /**
     * Clock out - update attendance record
     */
    public function clockOut(Attendance $attendance, UpdateAttendanceDTO $dto): Attendance;

    /**
     * Start lunch break
     */
    public function lunchBreakIn(Attendance $attendance): Attendance;

    /**
     * End lunch break
     */
    public function lunchBreakOut(Attendance $attendance): Attendance;

    /**
     * Find attendance record by ID
     */
    public function findAttendance(int $id): ?Attendance;

    /**
     * Find today's attendance for an employee
     */
    public function findTodayAttendance(string|int $employeeId, ?string $date = null): ?Attendance;

    /**
     * Find attendance in company
     */
    public function findAttendanceInCompany(int $id, int $companyId): ?Attendance;

    /**
     * Update attendance record
     */
    public function updateAttendance(Attendance $attendance, UpdateAttendanceDTO $dto): Attendance;

    /**
     * Delete attendance record
     */
    public function deleteAttendance(int $id): bool;

    /**
     * Get monthly attendance report for an employee
     */
    public function getMonthlyReport(int $employeeId, string $month, int $companyId): array;

    /**
     * Check if employee has clocked in today
     */
    public function hasClockedInToday(string|int $employeeId, ?string $date = null): bool;

    /**
     * Get attendance statistics for company
     */
    public function getAttendanceStatistics(int $companyId, ?string $fromDate = null, ?string $toDate = null): array;
}
