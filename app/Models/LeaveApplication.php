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
        'is_deducted',
        'place',
        'created_at',
        'include_holidays',
        'country_code',
        'policy_id',
        'service_years',
        'tier_order',
        'payment_percentage',
        'calculated_days',
        'documentation_provided',
        'salary_deduction_applied',
    ];
    protected $allowedFields = [
        'leave_id',
        'company_id',
        'employee_id',
        'leave_type_id',
        'from_date',
        'to_date',
        'particular_date',
        'leave_hours',
        'reason',
        'leave_month',
        'leave_year',
        'remarks',
        'status',
        'is_half_day',
        'leave_attachment',
        'created_at',
        'duty_employee_id',
        'is_deducted',
        'place'
    ];

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
        'is_deducted' => 'boolean',
        'place' => 'boolean',
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

    // get all leave types for this company
    public static function leave_types($companyId)
    {
        return ErpConstant::where('type', 'leave_type')
            ->when($companyId, function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->select(['constants_id', 'company_id', 'type', 'category_name'])
            ->get();
    }

    /**
     * Get the duty employee (replacement)
     */
    public function dutyEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'duty_employee_id', 'user_id');
    }


    // get all leave types names
    public function allLeaveTypeNameByCompanyId(int $companyId)
    {
        return \App\Models\ErpConstant::where('type', 'leave_type')
            ->where('company_id', $companyId)
            ->pluck('category_name', 'constants_id')
            ->toArray();
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

        // Format duty_employee data
        if (isset($this->dutyEmployee)) {
            $array['duty_employee'] = [
                'user_id' => $this->dutyEmployee->user_id,
                'first_name' => $this->dutyEmployee->first_name,
                'last_name' => $this->dutyEmployee->last_name,
                'full_name' => $this->dutyEmployee->full_name,
                'email' => $this->dutyEmployee->email,
                'department' => $this->dutyEmployee->user_details?->department?->name ?? null,
                'position' => $this->dutyEmployee->user_details?->designation?->name ?? null,
            ];
        }

        // Format leave_type data
        if (isset($this->leaveType)) {
            $array['leave_type'] = [
                'constants_id' => $this->leaveType->constants_id,
                'category_name' => $this->leaveType->category_name
            ];
        }

        // Format approvals data
        if (isset($this->approvals)) {
            $array['approvals'] = $this->approvals->map(function ($approval) {
                return [
                    'status' => $approval->status,
                    'approval_level' => $approval->approval_level ?? 1,
                    'updated_at' => $approval->updated_at,
                    'staff' => isset($approval->staff) ? [
                        'user_id' => $approval->staff->user_id,
                        'first_name' => $approval->staff->first_name,
                        'department' => $approval->staff->user_details?->department?->name ?? null,
                        'position' => $approval->staff->user_details?->designation?->name ?? null,
                        'last_name' => $approval->staff->last_name,
                        'full_name' => $approval->staff->full_name
                    ] : null
                ];
            })->toArray();
        }

        return $array;
    }
}
