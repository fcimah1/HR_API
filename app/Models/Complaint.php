<?php

namespace App\Models;

use App\Enums\NumericalStatusEnum;
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
    const STATUS_PENDING = 1;
    const STATUS_RESOLVED = 2;
    const STATUS_REJECTED = 3;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'complaint_from',
        'title',
        'complaint_date',
        'complaint_against',
        'description',
        'status',
        'notify_send_to',
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
        return $this->belongsTo(UserDetails::class, 'complaint_from', 'user_id');
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
}
