<?php

declare(strict_types=1);

namespace App\DTOs\EndOfService;

class CreateEndOfServiceDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $employeeId,
        public readonly string $hireDate,
        public readonly string $terminationDate,
        public readonly string $terminationType,
        public readonly int $serviceYears,
        public readonly int $serviceMonths,
        public readonly int $serviceDays,
        public readonly float $basicSalary,
        public readonly float $allowances,
        public readonly float $totalSalary,
        public readonly float $gratuityAmount,
        public readonly float $leaveCompensation,
        public readonly float $noticeCompensation,
        public readonly float $totalCompensation,
        public readonly float $unusedLeaveDays,
        public readonly int $calculatedBy,
        public readonly string $calculatedAt,
        public readonly ?string $notes = null,
    ) {}
}
