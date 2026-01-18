<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndOfService extends Model
{
    use HasFactory;

    protected $table = 'ci_erp_end_of_service_calculations';
    protected $primaryKey = 'calculation_id';

    protected $fillable = [
        'company_id',
        'employee_id',
        'hire_date',
        'termination_date',
        'termination_type',
        'service_years',
        'service_months',
        'service_days',
        'basic_salary',
        'allowances',
        'total_salary',
        'gratuity_amount',
        'leave_compensation',
        'notice_compensation',
        'total_compensation',
        'unused_leave_days',
        'calculated_by',
        'calculated_at',
        'notes',
        'is_approved',
        'approved_by',
        'approved_at'
    ];

    public $timestamps = true;

    protected $casts = [
        'hire_date' => 'date',
        'termination_date' => 'date',
        'basic_salary' => 'decimal:2',
        'gratuity_amount' => 'decimal:2',
        'total_compensation' => 'decimal:2',
        'service_years' => 'integer',
        'service_months' => 'integer',
        'service_days' => 'integer',
        'calculated_at' => 'datetime',
        'approved_at' => 'datetime',
        'is_approved' => 'boolean'
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by', 'user_id');
    }
}
