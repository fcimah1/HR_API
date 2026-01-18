<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model for Residence Renewal Costs
 * Table: ci_erp_residence_renewal_costs
 */
class ResidenceRenewalCost extends Model
{
    protected $table = 'ci_erp_residence_renewal_costs';
    protected $primaryKey = 'renewal_cost_id';
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'employee_id',
        'profession',
        'work_start_date',
        'previous_residence_expiry_date',
        'current_residence_expiry_date',
        'work_permit_fee',
        'residence_renewal_fees',
        'penalty_amount',
        'total_amount',
        'days_until_expiry',
        'renewal_period_days',
        'total_period_days',
        'daily_rate',
        'employee_share',
        'company_share',
        'grand_total',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'work_permit_fee' => 'decimal:2',
        'residence_renewal_fees' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'daily_rate' => 'decimal:3',
        'employee_share' => 'decimal:2',
        'company_share' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'work_start_date' => 'date',
        'previous_residence_expiry_date' => 'date',
        'current_residence_expiry_date' => 'date',
        'created_at' => 'datetime',
    ];

    /**
     * Get the employee associated with this cost record
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }
}
