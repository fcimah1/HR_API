<?php

declare(strict_types=1);

namespace App\DTOs\Report;

/**
 * DTO عام لفلترة التقارير
 * General Report Filter DTO
 */
class GeneralReportFilterDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly ?int $employeeId = null,
        public readonly ?int $branchId = null,
        public readonly ?int $departmentId = null,
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null,
        public readonly ?string $status = null,
        public readonly ?string $month = null, // YYYY-MM
        public readonly ?int $year = null, // YYYY
        public readonly ?array $employeeIds = null,
    ) {}

    /**
     * إنشاء DTO من Request
     */
    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            employeeId: isset($data['employee_id']) ? (int) $data['employee_id'] : null,
            branchId: isset($data['branch_id']) ? (int) $data['branch_id'] : null,
            departmentId: isset($data['department_id']) ? (int) $data['department_id'] : null,
            startDate: $data['start_date'] ?? null,
            endDate: $data['end_date'] ?? null,
            status: $data['status'] ?? null,
            month: $data['month'] ?? null,
            year: isset($data['year']) ? (int) $data['year'] : null,
            employeeIds: !empty($data['staffs']) ? $data['staffs'] : ($data['employee_ids'] ?? null),
        );
    }

    /**
     * تحويل إلى مصفوفة
     */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'branch_id' => $this->branchId,
            'department_id' => $this->departmentId,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'status' => $this->status,
            'month' => $this->month,
            'year' => $this->year,
        ];
    }
}
