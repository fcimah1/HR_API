<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    protected $table = 'ci_erp_notifications';
    protected $primaryKey = 'notification_id';
    public $timestamps = false;

    /**
     * Supported module options
     */
    const MODULE_ATTENDANCE = 'attendance_settings';
    const MODULE_LEAVE = 'leave_settings';
    const MODULE_LEAVE_ADJUSTMENT = 'leave_adjustment_settings';
    const MODULE_TRAVEL = 'travel_settings';
    const MODULE_OVERTIME = 'overtime_request_settings';
    const MODULE_LOAN = 'loan_request_settings';
    const MODULE_INCIDENT = 'incident_settings';
    const MODULE_TRANSFER = 'transfer_settings';
    const MODULE_WARNING = 'warning_settings';
    const MODULE_RESIGNATION = 'resignation_settings';
    const MODULE_COMPLAINT = 'complaint_settings';
    const MODULE_TERMINATION = 'termination_settings';
    const MODULE_CUSTODY_CLEARANCE = 'custody_clearance_settings';
    const MODULE_PROMOTIONS = 'promotions_settings';
    const MODULE_AWARDS = 'awards_settings';

    protected $fillable = [
        'company_id',
        'module_options',
        'notify_upon_submission',
        'notify_upon_approval',
        'approval_method',
        'approval_level',
        'approval_level01',
        'approval_level02',
        'approval_level03',
        'approval_level04',
        'approval_level05',
        'skip_specific_approval',
        'added_at',
        'updated_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'approval_level' => 'integer',
        'skip_specific_approval' => 'integer',
    ];

    /**
     * Relationship: Company
     */
    public function company()
    {
        return $this->belongsTo(User::class, 'company_id', 'user_id');
    }

    /**
     * Get notify_upon_submission as array
     * Supports keywords: 'manager', 'self', or numeric staff IDs
     */
    public function getNotifyUponSubmissionArrayAttribute(): array
    {
        if (empty($this->notify_upon_submission)) {
            return [];
        }

        $values = array_filter(array_map('trim', explode(',', $this->notify_upon_submission)));
        $staffIds = [];

        foreach ($values as $value) {
            // Skip '0' or empty values
            if ($value === '0' || empty($value)) {
                continue;
            }

            // If it's a number, add it directly
            if (is_numeric($value)) {
                $staffIds[] = (int)$value;
            } else {
                // Keep keywords like 'manager', 'self' to be handled by the calling code
                $staffIds[] = $value;
            }
        }

        return array_unique($staffIds);
    }

    /**
     * Get notify_upon_approval as array
     * Supports keywords: 'manager', 'self', or numeric staff IDs
     */
    public function getNotifyUponApprovalArrayAttribute(): array
    {
        if (empty($this->notify_upon_approval)) {
            return [];
        }

        $values = array_filter(array_map('trim', explode(',', $this->notify_upon_approval)));
        $staffIds = [];

        foreach ($values as $value) {
            // Skip '0' or empty values
            if ($value === '0' || empty($value)) {
                continue;
            }

            // If it's a number, add it directly
            if (is_numeric($value)) {
                $staffIds[] = (int)$value;
            } else {
                // Keep keywords like 'manager', 'self' to be handled by the calling code
                $staffIds[] = $value;
            }
        }

        return array_unique($staffIds);
    }

    /**
     * Get approver for specific level
     */
    public function getApproverForLevel(int $level): ?int
    {
        $field = 'approval_level0' . $level;
        return !empty($this->$field) ? (int)$this->$field : null;
    }

    /**
     * Scope: Filter by company
     */
    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope: Filter by module
     */
    public function scopeByModule($query, string $moduleOption)
    {
        return $query->where('module_options', $moduleOption);
    }

    /**
     * Check if multi-level approval is enabled
     */
    public function hasMultiLevelApproval(): bool
    {
        return !empty($this->approval_method) && (int)$this->approval_level > 0;
    }

    /**
     * Get total approval levels configured
     */
    public function getTotalApprovalLevels(): int
    {
        return (int)$this->approval_level ?? 0;
    }
}
