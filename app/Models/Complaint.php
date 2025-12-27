<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'ci_complaints';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'complaint_id';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * Status constants
     */
    const STATUS_PENDING = 0;
    const STATUS_RESOLVED = 1;
    const STATUS_REJECTED = 2;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'complaint_from',
        'title',
        'complaint_date',
        'complaint_against', // comma-separated user IDs
        'description',
        'status',
        'notify_send_to', // comma-separated user IDs
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'company_id' => 'integer',
        'complaint_from' => 'integer',
        'status' => 'integer',
    ];

    /**
     * Get the employee who filed the complaint.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'complaint_from', 'user_id');
    }

    /**
     * Get the user who added the complaint (alias for employee).
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'complaint_from', 'user_id');
    }

    /**
     * Get complaint against employee IDs as array
     */
    public function getComplaintAgainstIdsAttribute(): array
    {
        if (empty($this->complaint_against)) {
            return [];
        }
        return array_filter(array_map('intval', explode(',', $this->complaint_against)));
    }

    /**
     * Get notify send to employee IDs as array
     */
    public function getNotifySendToIdsAttribute(): array
    {
        if (empty($this->notify_send_to)) {
            return [];
        }
        return array_filter(array_map('intval', explode(',', $this->notify_send_to)));
    }

    /**
     * Get status text in Arabic.
     */
    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'قيد المراجعة',
            self::STATUS_RESOLVED => 'تم الحل',
            self::STATUS_REJECTED => 'مرفوض',
            default => 'غير محدد',
        };
    }

    /**
     * Get status text in English.
     */
    public function getStatusTextEnAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_RESOLVED => 'Resolved',
            self::STATUS_REJECTED => 'Rejected',
            default => 'Unknown',
        };
    }

    /**
     * Get the approvals for this complaint.
     */
    public function approvals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StaffApproval::class, 'module_key_id', 'complaint_id')
            ->where('module_option', 'complaint_settings');
    }
}
