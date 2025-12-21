<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OvertimeReasonEnum;
use App\Enums\CompensationTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeRequest extends Model
{
    use HasFactory;

    protected $table = 'ci_timesheet_request';
    protected $primaryKey = 'time_request_id';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'staff_id',
        'request_date',
        'request_month',
        'clock_in',
        'clock_out',
        'overtime_reason',
        'additional_work_hours',
        'straight',
        'time_a_half',
        'double_overtime',
        'compensation_type',
        'compensation_banked',
        'total_hours',
        'request_reason',
        'is_approved',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'company_id' => 'integer',
        'staff_id' => 'integer',
        'overtime_reason' => OvertimeReasonEnum::class,
        'additional_work_hours' => 'float',
        'compensation_type' => CompensationTypeEnum::class,
        'is_approved' => 'integer',
    ];

    /**
     * Get the employee who made the request.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id', 'user_id');
    }

    /**
     * Get the company.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(User::class, 'company_id', 'user_id');
    }

    /**
     * Get the approvals for this request.
     */
    public function approvals()
    {
        return $this->hasMany(StaffApproval::class, 'module_key_id', 'time_request_id')
            ->where('module_option', 'overtime_request_settings');
    }

    /**
     * Scope to filter pending requests.
     */
    public function scopePending($query)
    {
        return $query->where('is_approved', 0);
    }

    /**
     * Scope to filter approved requests.
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', 1);
    }

    /**
     * Scope to filter rejected requests.
     */
    public function scopeRejected($query)
    {
        return $query->where('is_approved', 2);
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to filter by employee.
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('staff_id', $employeeId);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, ?string $fromDate, ?string $toDate)
    {
        if ($fromDate) {
            $query->where('request_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('request_date', '<=', $toDate);
        }
        return $query;
    }

    /**
     * Scope to filter by month.
     */
    public function scopeForMonth($query, string $month)
    {
        return $query->where('request_month', $month);
    }

    /**
     * Get status text.
     */
    public function getStatusTextAttribute(): string
    {
        return match($this->is_approved) {
            0 => 'Pending',
            1 => 'Approved',
            2 => 'Rejected',
            default => 'Unknown',
        };
    }

    /**
     * Get overtime reason text.
     */
    public function getOvertimeReasonTextAttribute(): string
    {
        return $this->overtime_reason instanceof OvertimeReasonEnum 
            ? $this->overtime_reason->label() 
            : 'Unknown';
    }

    /**
     * Get overtime reason name (e.g., "STANDBY_PAY").
     */
    public function getOvertimeReasonNameAttribute(): string
    {
        return $this->overtime_reason instanceof OvertimeReasonEnum 
            ? $this->overtime_reason->name 
            : 'UNKNOWN';
    }

    /**
     * Get compensation type text.
     */
    public function getCompensationTypeTextAttribute(): string
    {
        return $this->compensation_type instanceof CompensationTypeEnum 
            ? $this->compensation_type->label() 
            : 'Unknown';
    }

    /**
     * Get compensation type name (e.g., "BANKED").
     */
    public function getCompensationTypeNameAttribute(): string
    {
        return $this->compensation_type instanceof CompensationTypeEnum 
            ? $this->compensation_type->name 
            : 'UNKNOWN';
    }

    /**
     * Get overtime reason Arabic label.
     */
    public function getOvertimeReasonTextArAttribute(): string
    {
        return $this->overtime_reason instanceof OvertimeReasonEnum 
            ? $this->overtime_reason->labelAr() 
            : 'غير معروف';
    }

    /**
     * Get compensation type Arabic label.
     */
    public function getCompensationTypeTextArAttribute(): string
    {
        return $this->compensation_type instanceof CompensationTypeEnum 
            ? $this->compensation_type->labelAr() 
            : 'غير معروف';
    }
}

