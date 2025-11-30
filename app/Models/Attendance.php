<?php

namespace App\Models;

use App\StatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'ci_timesheet';
    protected $primaryKey = 'time_attendance_id';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'employee_id',
        'attendance_date',
        'clock_in',
        'clock_in_ip_address',
        'clock_out',
        'clock_out_ip_address',
        'clock_in_out',
        'clock_in_latitude',
        'clock_in_longitude',
        'clock_out_latitude',
        'clock_out_longitude',
        'time_late',
        'early_leaving',
        'overtime',
        'total_work',
        'total_rest',
        'shift_id',
        'work_from_home',
        'lunch_breakin',
        'lunch_breakout',
        'attendance_status',
        'status',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'shift_id' => 'integer',
        'work_from_home' => 'integer',
        'clock_in_out' => 'string',
    ];

    // Status constants
    const STATUS_PENDING = StatusEnum::PENDING;
    const STATUS_APPROVED = StatusEnum::APPROVED;
    const STATUS_REJECTED = StatusEnum::REJECTED;

    /**
     * Get the employee who owns the attendance record
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    /**
     * Filter by company ID
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Filter by employee ID
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Filter by attendance date
     */
    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->where('attendance_date', $date);
    }

    /**
     * Filter by date range
     */
    public function scopeDateRange(Builder $query, string $fromDate, string $toDate): Builder
    {
        return $query->whereBetween('attendance_date', [$fromDate, $toDate]);
    }

    /**
     * Filter by status
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Filter pending records only
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Filter approved records only
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Filter work from home records
     */
    public function scopeWorkFromHome(Builder $query, bool $workFromHome = true): Builder
    {
        return $query->where('work_from_home', $workFromHome ? 1 : 0);
    }

    /**
     * Get today's attendance for an employee
     */
    public function scopeTodayForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId)
            ->where('attendance_date', now()->format('Y-m-d'));
    }
}
