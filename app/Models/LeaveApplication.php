<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApplication extends Model
{
    use HasFactory;

    protected $table = 'ci_leave_applications';
    protected $primaryKey = 'leave_id';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'employee_id',
        'duty_employee_id',
        'leave_type_id',
        'from_date',
        'to_date',
        'leave_hours',
        'particular_date',
        'leave_month',
        'leave_year',
        'reason',
        'remarks',
        'status',
        'is_half_day',
        'leave_attachment',
        'created_at',
    ];
    protected $allowedFields = ['leave_id','company_id','employee_id','leave_type_id','from_date','to_date','particular_date','leave_hours','reason','leave_month','leave_year','remarks','status','is_half_day','leave_attachment','created_at','duty_employee_id'];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'status' => 'boolean',
        'is_half_day' => 'boolean',
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'duty_employee_id' => 'integer',
        'leave_type_id' => 'integer',
    ];

    /**
     * Get the employee who applied for leave
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    /**
     * Get the duty employee (replacement)
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