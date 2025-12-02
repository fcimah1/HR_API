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
