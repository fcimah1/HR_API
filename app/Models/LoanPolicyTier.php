<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanPolicyTier extends Model
{
    use HasFactory;

    protected $table = 'ci_loan_policy_tiers';
    protected $primaryKey = 'tier_id';
    public $timestamps = false;

    protected $fillable = [
        'tier_name',
        'tier_name_ar',
        'tier_label_ar',
        'salary_multiplier',
        'max_months',
        'is_one_time',
        'is_active',
    ];

    protected $casts = [
        'salary_multiplier' => 'decimal:2',
        'max_months' => 'integer',
        'is_one_time' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get all advance salary requests for this tier
     */
    public function advanceSalaries(): HasMany
    {
        return $this->hasMany(AdvanceSalary::class, 'loan_tier_id', 'tier_id');
    }

    /**
     * Calculate the loan amount based on salary
     */
    public function calculateLoanAmount(float $salary): float
    {
        return round($salary * $this->salary_multiplier, 2);
    }

    /**
     * Calculate monthly installment
     */
    public function calculateMonthlyInstallment(float $salary, int $months): float
    {
        $loanAmount = $this->calculateLoanAmount($salary);
        return round($loanAmount / $months, 2);
    }

    /**
     * Calculate minimum months required to stay within 50% salary cap
     */
    public function calculateMinMonths(float $salary): int
    {
        $loanAmount = $this->calculateLoanAmount($salary);
        $maxDeduction = $salary * 0.50;

        // min_months = ceil(loan_amount / max_deduction)
        $minMonths = (int) ceil($loanAmount / $maxDeduction);

        // Can't be less than 1 or more than max_months
        return max(1, min($minMonths, $this->max_months));
    }

    /**
     * Check if requested months are valid for given salary
     */
    public function isValidMonths(float $salary, int $requestedMonths): bool
    {
        if ($requestedMonths < 1 || $requestedMonths > $this->max_months) {
            return false;
        }

        $monthlyInstallment = $this->calculateMonthlyInstallment($salary, $requestedMonths);
        $maxDeduction = $salary * 0.50;

        return $monthlyInstallment <= $maxDeduction;
    }

    /**
     * Scope: Only active tiers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get salary type based on tier (advance for tier 1, loan for others)
     */
    public function getSalaryType(): string
    {
        return $this->is_one_time ? 'advance' : 'loan';
    }

    /**
     * Get one_time_deduct value
     */
    public function getOneTimeDeductValue(): string
    {
        return $this->is_one_time ? '1' : '0';
    }
}
