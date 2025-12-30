<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resignation extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'ci_resignations';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'resignation_id';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * Status constants
     */
    const STATUS_PENDING = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'employee_id',
        'notice_date',
        'resignation_date',
        'document_file',
        'is_signed',
        'signed_file',
        'signed_date',
        'reason',
        'added_by',
        'status',
        'notify_send_to',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'is_signed' => 'integer',
        'added_by' => 'integer',
        'status' => 'integer',
    ];

    /**
     * Get the employee who submitted the resignation.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    /**
     * Get the user who added the resignation.
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by', 'user_id');
    }

    /**
     * Get the approvals for this resignation.
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(StaffApproval::class, 'module_key_id', 'resignation_id')
            ->where('module_option', 'resignation');
    }

    /**
     * Get status text in Arabic.
     */
    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'قيد المراجعة',
            self::STATUS_APPROVED => 'موافق عليها',
            self::STATUS_REJECTED => 'مرفوضة',
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
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            default => 'Unknown',
        };
    }
}
