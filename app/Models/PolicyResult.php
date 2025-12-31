<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyResult extends Model
{
    use HasFactory;

    protected $table = 'ci_policy_results';
    protected $primaryKey = 'result_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'company_id',
        'policy_id',
        'employee_id',
        'travel_id',
        'config_id',
        'hierarchy_level',
        'start_date',
        'end_date',
        'total_days',
        'daily_rate',
        'total_amount',
        'currency_base',
        'currency_local',
        'weekly_breakdown',
        'component_breakdown',
        'status',
        'payslip_id',
        'calculated_by',
        'created_at',
        'approved_at',
        'approved_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'weekly_breakdown' => 'array',
        'component_breakdown' => 'array',
        'created_at' => 'datetime',
        'approved_at' => 'datetime',
        'daily_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    public function calculatedBy()
    {
        return $this->belongsTo(User::class, 'calculated_by', 'user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by', 'user_id');
    }

    public function travel()
    {
        return $this->belongsTo(Travel::class, 'travel_id', 'travel_id');
    }

    /*
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function payslip()
    {
        return $this->belongsTo(Payslip::class, 'payslip_id', 'payslip_id');
    }
    */
}
