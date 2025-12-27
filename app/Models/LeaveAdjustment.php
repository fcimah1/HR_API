<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class LeaveAdjustment extends Model
{
    use HasFactory;

    protected $table = 'ci_leave_adjustment';
    protected $primaryKey = 'adjustment_id';
    public $timestamps = false;

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'duty_employee_id',
    ];

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type_id',
        'adjust_hours',
        'reason_adjustment',
        'status',
        'created_at',
        'adjustment_date',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'leave_type_id' => 'integer',
        'status' => 'integer',
        'adjustment_date' => 'date',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;

    /**
     * Get all available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'قيد المراجعة',
            self::STATUS_APPROVED => 'موافق عليه',
            self::STATUS_REJECTED => 'مرفوض',
        ];
    }

    /**
     * Get the employee
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }



    /**
     * Get the leave type
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(ErpConstant::class, 'leave_type_id', 'constants_id');
    }

    /**
     * Get the approvals for this leave adjustment
     */
    public function approvals()
    {
        return $this->hasMany(StaffApproval::class, 'module_key_id', 'adjustment_id')
            ->where('module_option', 'leave_adjustment_settings');
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
     * Filter pending adjustments only
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Filter approved adjustments only
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Custom toArray method to format the response
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        // Format employee data
        if (isset($this->employee)) {
            $array['employee'] = [
                'user_id' => $this->employee->user_id,
                'first_name' => $this->employee->first_name,
                'last_name' => $this->employee->last_name,
                'full_name' => $this->employee->full_name,
                'email' => $this->employee->email,
                'department' => $this->employee->user_details?->department?->name ?? null,
                'position' => $this->employee->user_details?->designation?->name ?? null,
            ];
        }


        // Format leave_type data
        if (isset($this->leaveType)) {
            $array['leave_type'] = [
                'constants_id' => $this->leaveType->constants_id,
                'category_name' => $this->leaveType->category_name
            ];
        }
        if (isset($this->approvals)) {
            $array['approvals'] = $this->approvals->map(function ($approval) {
                return [
                    'status' => $approval->status,
                    'approval_level' => $approval->approval_level ?? 1,
                    'updated_at' => $approval->updated_at,
                    'staff' => isset($approval->staff) ? [
                        'user_id' => $approval->staff->user_id,
                        'first_name' => $approval->staff->first_name,
                        'last_name' => $approval->staff->last_name,
                        'full_name' => $approval->staff->full_name,
                        'department' => $approval->staff->user_details?->department?->name ?? null,
                        'position' => $approval->staff->user_details?->designation?->name ?? null,
                    ] : null
                ];
            })->toArray();
        }

        return $array;
    }
}
