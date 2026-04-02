<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\ErpConstant;
use App\Models\LeaveCountryPolicy;
use App\Repository\Interface\LeavePolicyRepositoryInterface;
use Exception;
use Illuminate\Support\Facades\DB;

class LeavePolicyRepository implements LeavePolicyRepositoryInterface
{
    /**
     * Get policies for a specific country
     */
    public function getPoliciesForCountry(string $countryCode, int $companyId = 0): array
    {
        return LeaveCountryPolicy::forCountry($countryCode)
            ->where('company_id', $companyId)
            ->get()
            ->groupBy('leave_type')
            ->toArray();
    }

    /**
     * Find matching policy for employee based on service years
     */
    public function findMatchingPolicy(
        string $countryCode,
        string $leaveType,
        float $serviceYears,
        int $companyId
    ): ?LeaveCountryPolicy {
        $policies = LeaveCountryPolicy::forCountry($countryCode)
            ->forLeaveType($leaveType)
            ->where('company_id', $companyId)
            ->orderBy('tier_order')
            ->get();

        foreach ($policies as $policy) {
            if ($policy->appliesTo($serviceYears)) {
                return $policy;
            }
        }

        return null;
    }

    /**
     * Get all tiers for a leave type in a country
     */
    public function getTiersForLeaveType(string $countryCode, string $leaveType, int $companyId = 0): array
    {
        return LeaveCountryPolicy::forCountry($countryCode)
            ->forLeaveType($leaveType)
            ->where('company_id', $companyId)
            ->orderBy('tier_order')
            ->get()
            ->toArray();
    }

    /**
     * Get total quota days for a leave type
     */
    public function getTotalQuotaDays(string $countryCode, string $leaveType, int $companyId = 0): int
    {
        return (int) LeaveCountryPolicy::forCountry($countryCode)
            ->forLeaveType($leaveType)
            ->where('company_id', $companyId)
            ->sum('entitlement_days');
    }

    /**
     * Get one-time leave policies only
     */
    public function getOneTimePolicies(string $countryCode, int $companyId = 0): array
    {
        return LeaveCountryPolicy::forCountry($countryCode)
            ->oneTime()
            ->where('company_id', $companyId)
            ->get()
            ->toArray();
    }

    /**
     * Get all policies for a specific company
     */
    public function getAllPoliciesForCompany(int $companyId)
    {
        return LeaveCountryPolicy::where('company_id', $companyId)
            ->orderBy('leave_type')
            ->orderBy('tier_order')
            ->get();
    }

    /**
     * Get system default policies for a country
     */
    public function getSystemPoliciesForCountry(string $countryCode)
    {
        return LeaveCountryPolicy::forCountry($countryCode)
            ->where('company_id', 0)
            ->orderBy('leave_type')
            ->orderBy('tier_order')
            ->get();
    }

    /**
     * Delete all company-specific policies
     * Returns total number of deleted rows (ErpConstants + Policies)
     */
    public function deleteCompanyPolicies(int $companyId): int
    {
        // Delete leave types from ci_erp_constants for this company
        $deletedConstants = ErpConstant::where('company_id', $companyId)
            ->where('type', 'leave_type')
            ->delete();

        // Delete policies from ci_leave_policy_countries
        $deletedPolicies = LeaveCountryPolicy::where('company_id', $companyId)
            ->where('company_id', '!=', 0)
            ->delete();

        return $deletedConstants + $deletedPolicies;
    }

    /**
     * Create a new policy
     */
    public function create(array $data): LeaveCountryPolicy
    {
        return LeaveCountryPolicy::create($data);
    }
}
