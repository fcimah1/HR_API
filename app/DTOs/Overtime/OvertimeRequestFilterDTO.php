<?php

namespace App\DTOs\Overtime;

class OvertimeRequestFilterDTO
{
    public function __construct(
        public readonly ?int $employeeId = null,
        public readonly ?array $employeeIds = null,
        public readonly ?int $status = null, // 0=pending, 1=approved, 2=rejected
        public readonly ?int $overtimeReason = null,
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly ?string $month = null, // Format: YYYY-MM
        public readonly ?int $companyId = null,
        public readonly ?array $hierarchyLevels = null, // For hierarchy filtering
        public readonly ?string $search = '',
        public readonly int $perPage = 15,
        public readonly int $page = 1,
    ) {}

    /**
     * Create DTO from request parameters.
     */
    public static function fromRequest(array $params): self
    {
        return new self(
            employeeId: isset($params['employee_id']) ? (int) $params['employee_id'] : null,
            employeeIds: $params['employee_ids'] ?? null,
            status: isset($params['status']) ? (int) $params['status'] : null,
            overtimeReason: isset($params['overtime_reason']) ? (int) $params['overtime_reason'] : null,
            fromDate: $params['from_date'] ?? null,
            toDate: $params['to_date'] ?? null,
            month: $params['month'] ?? null,
            companyId: isset($params['company_id']) ? (int) $params['company_id'] : null,
            hierarchyLevels: $params['hierarchy_levels'] ?? null,
            search: $params['search'] ?? '',
            perPage: isset($params['per_page']) ? (int) $params['per_page'] : 15,
            page: isset($params['page']) ? (int) $params['page'] : 1,
        );
    }

    /**
     * Check if any filter is applied.
     */
    public function hasFilters(): bool
    {
        return $this->employeeId !== null
            || $this->employeeIds !== null
            || $this->status !== null
            || $this->overtimeReason !== null
            || $this->fromDate !== null
            || $this->toDate !== null
            || $this->month !== null
            || $this->search !== '';
    }

    public function toArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'employee_ids' => $this->employeeIds,
            'status' => $this->status,
            'overtime_reason' => $this->overtimeReason,
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
            'month' => $this->month,
            'company_id' => $this->companyId,
            'hierarchy_levels' => $this->hierarchyLevels,
            'search' => $this->search,
            'per_page' => $this->perPage,
            'page' => $this->page,
        ];
    }
}
