<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Employee Leave Balance Model
 * 
 * Tracks employee leave balances per leave type and year
 * Automatically calculated and updated based on usage
 * 
 * @property int $balance_id
 * @property int $company_id
 * @property int $employee_id
 * @property string $leave_type
 * @property int $year
 * @property float $total_entitled
 * @property float $used_days
 * @property float $pending_days
 * @property float $remaining_days
 * @property float $carried_forward
 * @property string $last_calculated
 */
class EmployeeLeaveBalance extends Model
{
    protected $table = 'ci_employee_leave_balances';
    protected $primaryKey = 'balance_id';
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type',
        'year',
        'total_entitled',
        'used_days',
        'pending_days',
        'remaining_days',
        'carried_forward',
    ];

    protected $casts = [
        'balance_id' => 'integer',
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'year' => 'integer',
        'total_entitled' => 'float',
        'used_days' => 'float',
        'pending_days' => 'float',
        'remaining_days' => 'float',
        'carried_forward' => 'float',
        'last_calculated' => 'datetime',
    ];

    /**
     * Get the employee associated with this balance
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    /**
     * Scope: Get balances for a specific employee
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope: Get balances for a specific year
     */
    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope: Get balances for a specific leave type
     */
    public function scopeForLeaveType($query, string $leaveType)
    {
        return $query->where('leave_type', $leaveType);
    }

    /**
     * Update remaining days based on used and pending
     */
    public function recalculateRemaining(): void
    {
        $this->remaining_days = $this->total_entitled + $this->carried_forward - $this->used_days - $this->pending_days;
        $this->save();
    }
}
