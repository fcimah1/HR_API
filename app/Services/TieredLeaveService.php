<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\LeavePolicyRepository;
use App\Repository\LeaveRepository;
use Carbon\Carbon;

/**
 * Tiered Leave Service
 * 
 * Handles tiered sick leave calculations (Saudi Arabia, Qatar)
 * Splits leave days across multiple tiers with different payment percentages
 */
class TieredLeaveService
{
    private LeavePolicyRepository $policyRepository;
    private LeaveRepository $leaveRepository;

    public function __construct(
        LeavePolicyRepository $policyRepository,
        LeaveRepository $leaveRepository
    ) {
        $this->policyRepository = $policyRepository;
        $this->leaveRepository = $leaveRepository;
    }

    /**
     * Get cumulative sick days used by employee in a year
     * 
     * @param int $employeeId
     * @param int $year Year to check (e.g., 2026)
     * @param string $leaveType System leave type (usually 'sick')
     * @return float Total days used
     */
    public function getCumulativeSickDaysUsed(int $employeeId, int $year, string $leaveType = 'sick'): float
    {
        return $this->leaveRepository->getCumulativeDaysUsed($employeeId, $year, $leaveType);
    }

    /**
     * Calculate tier split for a leave request
     * 
     * @param float $cumulativeDays Days already used this year
     * @param float $requestedDays Days being requested
     * @param string $countryCode Country code (SA, QA)
     * @param string $leaveType Leave type
     * @return array Array of tier splits with payment info
     */
    public function calculateTierSplit(
        float $cumulativeDays,
        float $requestedDays,
        string $countryCode,
        string $leaveType = 'sick'
    ): array {
        // Get all tiers for this country and leave type
        $tiers = $this->policyRepository->getTiersForLeaveType($countryCode, $leaveType, 0);

        if (empty($tiers)) {
            return [];
        }

        $splits = [];
        $remainingDays = $requestedDays;
        $currentPosition = $cumulativeDays;

        foreach ($tiers as $tierData) {
            if ($remainingDays <= 0) {
                break;
            }

            // Calculate cumulative tier boundaries
            $tierStart = 0;
            foreach ($tiers as $previousTier) {
                if ($previousTier['tier_order'] < $tierData['tier_order']) {
                    $tierStart += $previousTier['entitlement_days'];
                }
            }

            $tierEnd = $tierStart + $tierData['entitlement_days'];

            // Check if current position falls within this tier
            if ($currentPosition < $tierEnd) {
                // Calculate how many days fall in this tier
                $availableInTier = $tierEnd - max($currentPosition, $tierStart);
                $daysInTier = min($remainingDays, $availableInTier);

                if ($daysInTier > 0) {
                    $splits[] = [
                        'tier_order' => $tierData['tier_order'],
                        'tier_start' => $tierStart + 1,
                        'tier_end' => $tierEnd,
                        'days_in_tier' => $daysInTier,
                        'payment_percentage' => $tierData['payment_percentage'],
                        'deduction_percentage' => 100 - $tierData['payment_percentage'],
                        'tier_description_ar' => $tierData['policy_description_ar'] ?? '',
                    ];

                    $remainingDays -= $daysInTier;
                    $currentPosition += $daysInTier;
                }
            }
        }

        return $splits;
    }

    /**
     * Get tiered payment info for a leave request
     * 
     * @param string $countryCode
     * @param string $leaveType
     * @param float $cumulativeDays
     * @param float $requestedDays
     * @return array Primary tier info
     */
    public function getTieredPaymentInfo(
        string $countryCode,
        string $leaveType,
        float $cumulativeDays,
        float $requestedDays
    ): array {
        $splits = $this->calculateTierSplit($cumulativeDays, $requestedDays, $countryCode, $leaveType);

        if (empty($splits)) {
            // Default values if no tier found
            return [
                'tier_order' => 1,
                'payment_percentage' => 100,
                'deduction_percentage' => 0,
                'is_split' => false,
                'splits' => [],
            ];
        }

        // If request spans multiple tiers
        if (count($splits) > 1) {
            // Use the first tier as primary
            $primaryTier = $splits[0];
            return [
                'tier_order' => $primaryTier['tier_order'],
                'payment_percentage' => $primaryTier['payment_percentage'],
                'deduction_percentage' => $primaryTier['deduction_percentage'],
                'is_split' => true,
                'splits' => $splits,
            ];
        }

        // Single tier
        $tier = $splits[0];
        return [
            'tier_order' => $tier['tier_order'],
            'payment_percentage' => $tier['payment_percentage'],
            'deduction_percentage' => $tier['deduction_percentage'],
            'is_split' => false,
            'splits' => $splits,
        ];
    }

    /**
     * Get total quota for a leave type in a country
     * 
     * @param string $countryCode
     * @param string $leaveType
     * @return int Total days across all tiers
     */
    public function getTotalQuota(string $countryCode, string $leaveType): int
    {
        return $this->policyRepository->getTotalQuotaDays($countryCode, $leaveType, 0);
    }

    /**
     * Check if leave exceeds total quota
     * 
     * @param float $cumulativeDays
     * @param float $requestedDays
     * @param string $countryCode
     * @param string $leaveType
     * @return array ['exceeded' => bool, 'total_quota' => int, 'remaining' => float]
     */
    public function checkQuotaExceeded(
        float $cumulativeDays,
        float $requestedDays,
        string $countryCode,
        string $leaveType
    ): array {
        $totalQuota = $this->getTotalQuota($countryCode, $leaveType);
        $afterRequest = $cumulativeDays + $requestedDays;

        return [
            'exceeded' => $afterRequest > $totalQuota,
            'total_quota' => $totalQuota,
            'remaining' => max(0, $totalQuota - $cumulativeDays),
        ];
    }
}
