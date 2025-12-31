<?php

namespace App\Models;

use App\Enums\NumericalStatusEnum;
use App\Enums\StringStatusEnum;
use Illuminate\Database\Eloquent\Model;

class NotificationStatus extends Model
{
    protected $table = 'ci_erp_notifications_status';
    protected $primaryKey = 'notification_status_id';
    public $timestamps = false;

    /**
     * Module status options
     */
    const STATUS_SUBMITTED = StringStatusEnum::SUBMITTED->value;
    const STATUS_PENDING = NumericalStatusEnum::PENDING->value;
    const STATUS_APPROVED = NumericalStatusEnum::APPROVED->value;
    const STATUS_REJECTED = NumericalStatusEnum::REJECTED->value;

    protected $fillable = [
        'module_option',
        'module_status',
        'module_key_id',
        'staff_id',
        'is_read',
        'additional_data',
    ];

    protected $casts = [
        'staff_id' => 'integer',
        'is_read' => 'integer',
    ];

    /**
     * Relationship: Staff/User
     */
    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id', 'user_id');
    }

    /**
     * Relationship: Travel (when module_option is travel_settings)
     */
    public function travel()
    {
        return $this->belongsTo(Travel::class, 'module_key_id', 'travel_id');
    }

    /**
     * Get policy result for travel notifications
     * Returns the travel allowance based on employee hierarchy level
     */
    public function getPolicyResultAttribute()
    {
        if ($this->module_option !== 'travel_settings') {
            return null;
        }

        $travel = $this->travel;
        if (!$travel) {
            return null;
        }

        // Get employee's hierarchy level
        $employeeHierarchyLevel = $travel->employee
            ?->user_details
            ?->designation
            ?->hierarchy_level;

        if (!$employeeHierarchyLevel) {
            return null;
        }

        return PolicyResult::where('policy_id', 1) // 1 = Travel
            ->where('hierarchy_level', $employeeHierarchyLevel)
            ->where('company_id', $travel->company_id)
            ->first();
    }

    /**
     * Scope: Filter unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', 0);
    }

    /**
     * Scope: Filter read notifications
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', 1);
    }

    /**
     * Scope: Filter by staff
     */
    public function scopeByStaff($query, int $staffId)
    {
        return $query->where('staff_id', $staffId);
    }

    /**
     * Scope: Filter by module
     */
    public function scopeByModule($query, string $moduleOption)
    {
        return $query->where('module_option', $moduleOption);
    }

    /**
     * Scope: Filter by module and key
     */
    public function scopeByModuleKey($query, string $moduleOption, string $moduleKeyId)
    {
        return $query->where('module_option', $moduleOption)
            ->where('module_key_id', $moduleKeyId);
    }

    /**
     * Mark as read
     */
    public function markAsRead(): bool
    {
        $this->is_read = 1;
        return $this->save();
    }

    /**
     * Check if notification is read
     */
    public function isRead(): bool
    {
        return $this->is_read === 1;
    }
}
