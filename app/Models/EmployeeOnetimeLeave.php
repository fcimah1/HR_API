<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Employee One-Time Leave Model
 * 
 * Tracks one-time leave usage (e.g., Hajj leave)
 * Prevents employees from taking these leaves more than once
 * 
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property string $leave_type
 * @property int|null $leave_application_id
 * @property string $taken_date
 */
class EmployeeOnetimeLeave extends Model
{
    protected $table = 'ci_employee_onetime_leaves';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type',
        'leave_application_id',
        'taken_date',
    ];

    protected $casts = [
        'id' => 'integer',
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'leave_application_id' => 'integer',
        'taken_date' => 'date',
        'created_at' => 'datetime',
    ];

    /**
     * Get the employee associated with this one-time leave
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    /**
     * Get the leave application associated with this record
     */
    public function leaveApplication(): BelongsTo
    {
        return $this->belongsTo(LeaveApplication::class, 'leave_application_id', 'leave_id');
    }

    /**
     * Scope: Get records for a specific employee
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope: Get records for a specific leave type
     */
    public function scopeForLeaveType($query, string $leaveType)
    {
        return $query->where('leave_type', $leaveType);
    }

    /**
     * Check if employee has already used this one-time leave
     */
    public static function hasUsed(int $employeeId, string $leaveType): bool
    {
        return self::where('employee_id', $employeeId)
            ->where('leave_type', $leaveType)
            ->exists();
    }
}
