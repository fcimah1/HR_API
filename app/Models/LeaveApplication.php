<?php

namespace App\Models;

use App\Enums\NumericalStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class LeaveApplication extends Model
{
    use HasFactory;

    protected $table = 'ci_leave_applications';
    protected $primaryKey = 'leave_id';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'employee_id',
        'duty_employee_id',
        'leave_type_id',
        'from_date',
        'to_date',
        'leave_hours',
        'particular_date',
        'leave_month',
        'leave_year',
        'reason',
        'remarks',
        'status',
        'is_half_day',
        'leave_attachment',
        'created_at',
    ];
    protected $allowedFields = ['leave_id', 'company_id', 'employee_id', 'leave_type_id', 'from_date', 'to_date', 'particular_date', 'leave_hours', 'reason', 'leave_month', 'leave_year', 'remarks', 'status', 'is_half_day', 'leave_attachment', 'created_at', 'duty_employee_id'];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'status' => 'integer',
        'is_half_day' => 'boolean',
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'duty_employee_id' => 'integer',
        'leave_type_id' => 'integer',
    ];


    public const STATUS_PENDING = NumericalStatusEnum::PENDING->value;
    public const STATUS_APPROVED = NumericalStatusEnum::APPROVED->value;
    public const STATUS_REJECTED = NumericalStatusEnum::REJECTED->value;

    /**
     * Get the employee who applied for leave
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    /**
     * Get the duty employee (replacement)
     */
    public function dutyEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'duty_employee_id', 'user_id');
    }

    /**
     * Get the leave type
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(ErpConstant::class, 'leave_type_id', 'constants_id');
    }

    /**
     * Get the approvals for this leave application
     */
    public function approvals()
    {
        return $this->hasMany(StaffApproval::class, 'module_key_id', 'leave_id')
            ->where('module_option', 'leave_settings');
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
     * Filter by status
     */
    public function scopeWithStatus(Builder $query, int $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Filter pending applications only
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Filter approved applications only
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }
}
