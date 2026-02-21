<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\Models\LeaveCountryPolicy;

interface LeavePolicyRepositoryInterface
{
    public function getPoliciesForCountry(string $countryCode, int $companyId = 0): array;

    public function findMatchingPolicy(
        string $countryCode,
        string $leaveType,
        float $serviceYears,
        int $companyId
    ): ?LeaveCountryPolicy;

    public function getTiersForLeaveType(string $countryCode, string $leaveType, int $companyId = 0): array;

    public function getTotalQuotaDays(string $countryCode, string $leaveType, int $companyId = 0): int;

    public function getOneTimePolicies(string $countryCode, int $companyId = 0): array;
}
