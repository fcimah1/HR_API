<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffApproval extends Model
{
    use HasFactory;

    protected $table = 'ci_erp_notifications_approval';
    protected $primaryKey = 'staff_approval_id';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'staff_id',
        'module_option',
        'module_key_id',
        'status',
        'approval_level',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'company_id' => 'integer',
        'staff_id' => 'integer',
        'status' => 'integer',
    ];

    /**
     * Get the staff member who approved.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id', 'user_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the leave application for this approval.
     */
    public function leaveApplication()
    {
        return $this->belongsTo(LeaveApplication::class, 'module_key_id', 'leave_id');
    }
    
    /**
     * Get the leave adjustment for this approval.
     */
    public function leaveAdjustment()
    {
        return $this->belongsTo(LeaveAdjustment::class, 'module_key_id', 'leave_adjustment_id');
    }

    /**
     * Get the leave adjustment for this approval.
     */
    public function travelRequest()
    {
        return $this->belongsTo(Travel::class, 'module_key_id', 'travel_request_id');
    }

    /**
     * Get the overtime request for this approval.
     */
    public function overtimeRequest(): BelongsTo
    {
        return $this->belongsTo(OvertimeRequest::class, 'module_key_id', 'time_request_id');
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class, 'module_key_id', 'attendance_id');
    }

    public function advanceSalary()
    {
        return $this->belongsTo(AdvanceSalary::class, 'module_key_id', 'advance_salary_id');
    }

    /**
     * Scope to filter overtime approvals only.
     */
    public function scopeForOvertime($query)
    {
        return $query->where('module_option', 'overtime_request_settings');
    }

    /**
     * Scope to filter by request ID.
     */
    public function scopeForRequest($query, int $requestId)
    {
        return $query->where('module_key_id', $requestId);
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to filter approved records.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope to filter rejected records.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 2);
    }

    /**
     * Scope to filter final approvals.
     */
    public function scopeFinalApproval($query)
    {
        return $query->where('approval_level', '1');
    }

    /**
     * Scope to filter intermediate approvals.
     */
    public function scopeIntermediateApproval($query)
    {
        return $query->where('approval_level', '0');
    }
}

