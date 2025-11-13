<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveAdjustment extends Model
{
    use HasFactory;

    protected $table = 'ci_leave_adjustment';
    protected $primaryKey = 'adjustment_id';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'employee_id',
        'duty_employee_id',
        'leave_type_id',
        'adjust_hours',
        'reason_adjustment',
        'status',
        'created_at',
        'adjustment_date',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'duty_employee_id' => 'integer',
        'leave_type_id' => 'integer',
        'status' => 'integer',
        'adjustment_date' => 'date',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;

    /**
     * Get all available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'قيد المراجعة',
            self::STATUS_APPROVED => 'موافق عليه',
            self::STATUS_REJECTED => 'مرفوض',
        ];
    }

    /**
     * Get the employee
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    /**
     * Get the duty employee
     */
    public function dutyEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'duty_employee_id', 'user_id');
    }

    /**
     * Get the leave type
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(ErpConstant::class, 'leave_type_id', 'constants_id');
    }
}
