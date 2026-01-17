<?php

declare(strict_types=1);

namespace App\DTOs\Report;

/**
 * DTO لفلترة تقارير الموظفين
 * Employee Report Filter DTO
 */
class EmployeeReportFilterDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly ?int $branchId = null,
        public readonly ?int $departmentId = null,
        public readonly ?string $countryId = null,
        public readonly ?string $employmentType = null, // full_time, part_time, contract
        public readonly ?string $status = null, // active, inactive
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null,
    ) {}

    /**
     * إنشاء DTO من Request
     */
    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            branchId: isset($data['branch_id']) ? (int) $data['branch_id'] : null,
            departmentId: isset($data['department_id']) ? (int) $data['department_id'] : null,
            countryId: $data['country_id'] ?? null,
            employmentType: $data['employment_type'] ?? null,
            status: $data['status'] ?? null,
            startDate: $data['start_date'] ?? null,
            endDate: $data['end_date'] ?? null,
        );
    }

    /**
     * تحويل إلى مصفوفة
     */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'branch_id' => $this->branchId,
            'department_id' => $this->departmentId,
            'country_id' => $this->countryId,
            'employment_type' => $this->employmentType,
            'status' => $this->status,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
        ];
    }
}
