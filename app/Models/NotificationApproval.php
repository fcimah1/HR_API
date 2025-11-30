<?php

namespace App\Models;

use App\Enums\NumericalStatusEnum;
use Illuminate\Database\Eloquent\Model;

class NotificationApproval extends Model
{
    protected $table = 'ci_erp_notifications_approval';
    protected $primaryKey = 'staff_approval_id';
    public $timestamps = false;

    /**
     * Approval status constants
     */
    const STATUS_PENDING = NumericalStatusEnum::PENDING->value;
    const STATUS_APPROVED = NumericalStatusEnum::APPROVED->value;
    const STATUS_REJECTED = NumericalStatusEnum::REJECTED->value;

    protected $fillable = [
        'company_id',
        'staff_id',
        'module_option',
        'module_key_id',
        'status',
        'approval_level',
        'updated_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'staff_id' => 'integer',
        'status' => 'integer',
    ];

    /**
     * Relationship: Company
     */
    public function company()
    {
        return $this->belongsTo(User::class, 'company_id', 'user_id');
    }

    /**
     * Relationship: Staff/Approver
     */
    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id', 'user_id');
    }

    /**
     * Scope: Filter by module
     */
    public function scopeByModule($query, string $moduleOption)
    {
        return $query->where('module_option', $moduleOption);
    }

    /**
     * Scope: Filter by request (module + key)
     */
    public function scopeByRequest($query, string $moduleOption, string $moduleKeyId)
    {
        return $query->where('module_option', $moduleOption)
            ->where('module_key_id', $moduleKeyId);
    }

    /**
     * Scope: Filter by company
     */
    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope: Filter pending approvals
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope: Filter approved
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope: Filter rejected
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Check if approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
