<?php

namespace App\DTOs\AdvanceSalary;

class AdvanceSalaryFilterDTO
{
    public function __construct(
        public readonly ?int $companyId = null,
        public readonly ?int $employeeId = null,
        public readonly ?string $salaryType = null,
        public readonly ?int $status = null,
        public readonly ?string $monthYear = null,
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly int $perPage = 15,
        public readonly int $page = 1,
        public readonly string $sortBy = 'created_at',
        public readonly string $sortDirection = 'desc'
    ) {}

    public static function fromRequest(array $data): self
    {
        // Handle status conversion properly
        $status = null;
        if (isset($data['status'])) {
            $status = (int) $data['status'];
        }
        
        return new self(
            companyId: isset($data['company_id']) ? (int) $data['company_id'] : null,
            employeeId: isset($data['employee_id']) ? (int) $data['employee_id'] : null,
            salaryType: $data['type'] ?? $data['salary_type'] ?? null,
            status: $status,
            monthYear: $data['month_year'] ?? null,
            fromDate: $data['from_date'] ?? null,
            toDate: $data['to_date'] ?? null,
            perPage: (int) ($data['per_page'] ?? 15),
            page: (int) ($data['page'] ?? 1),
            sortBy: $data['sort_by'] ?? 'created_at',
            sortDirection: $data['sort_direction'] ?? 'desc'
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'salary_type' => $this->salaryType,
            'status' => $this->status,
            'month_year' => $this->monthYear,
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
            'per_page' => $this->perPage,
            'page' => $this->page,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
        ];
    }
}

