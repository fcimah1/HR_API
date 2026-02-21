<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Leave Country Policy Model
 * 
 * Represents country-specific leave policies for different leave types
 * Supports tiered policies (e.g., sick leave with multiple tiers)
 * 
 * @property int $policy_id
 * @property int $company_id
 * @property string $country_code
 * @property string $leave_type
 * @property int $tier_order
 * @property float $service_years_min
 * @property float|null $service_years_max
 * @property int $entitlement_days
 * @property bool $is_paid
 * @property int $payment_percentage
 * @property int|null $max_consecutive_days
 * @property bool $requires_documentation
 * @property int|null $documentation_after_days
 * @property bool $is_one_time
 * @property bool $deduct_from_annual
 * @property string|null $policy_description_en
 * @property string|null $policy_description_ar
 * @property bool $is_active
 */
class LeaveCountryPolicy extends Model
{
    protected $table = 'ci_leave_policy_countries';
    protected $primaryKey = 'policy_id';
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'country_code',
        'leave_type',
        'tier_order',
        'service_years_min',
        'service_years_max',
        'entitlement_days',
        'is_paid',
        'payment_percentage',
        'max_consecutive_days',
        'requires_documentation',
        'documentation_after_days',
        'is_one_time',
        'deduct_from_annual',
        'policy_description_en',
        'policy_description_ar',
        'is_active',
    ];

    protected $casts = [
        'policy_id' => 'integer',
        'company_id' => 'integer',
        'tier_order' => 'integer',
        'service_years_min' => 'float',
        'service_years_max' => 'float',
        'entitlement_days' => 'integer',
        'is_paid' => 'boolean',
        'payment_percentage' => 'integer',
        'max_consecutive_days' => 'integer',
        'requires_documentation' => 'boolean',
        'documentation_after_days' => 'integer',
        'is_one_time' => 'boolean',
        'deduct_from_annual' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Scope: Get policies for a specific country
     */
    public function scopeForCountry($query, string $countryCode)
    {
        return $query->where('country_code', $countryCode)
            ->where('is_active', 1)
            ->orderBy('leave_type')
            ->orderBy('tier_order');
    }

    /**
     * Scope: Get system default policies (company_id = 0)
     */
    public function scopeSystemDefaults($query)
    {
        return $query->where('company_id', 0);
    }

    /**
     * Scope: Get policies for a specific leave type
     */
    public function scopeForLeaveType($query, string $leaveType)
    {
        return $query->where('leave_type', $leaveType);
    }

    /**
     * Scope: Get one-time leave policies only
     */
    public function scopeOneTime($query)
    {
        return $query->where('is_one_time', 1);
    }

    /**
     * Check if this policy applies to given service years
     */
    public function appliesTo(float $serviceYears): bool
    {
        if ($serviceYears < $this->service_years_min) {
            return false;
        }

        if ($this->service_years_max !== null && $serviceYears >= $this->service_years_max) {
            return false;
        }

        return true;
    }
}
