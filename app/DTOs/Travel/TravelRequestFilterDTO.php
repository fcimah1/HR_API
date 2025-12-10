<?php

namespace App\DTOs\Travel;

class TravelRequestFilterDTO
{
    public function __construct(
        public readonly ?int $employeeId = null,
        public readonly ?array $employeeIds = null,
        public readonly ?int $status = null, // 0=pending, 1=approved, 2=rejected
        public readonly ?int $travelMode = null, // 1-bus, 2-train, 3-plane, 4-taxi, 5-rental_car
        public readonly ?int $arrangementType = null,
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null,
        public readonly ?string $month = null, // Format: YYYY-MM
        public readonly ?int $companyId = null,
        public readonly ?array $hierarchyLevels = null, // For hierarchy filtering
        public readonly ?string $search = '',
        public readonly int $perPage = 15,
        public readonly int $page = 1,
        public readonly string $orderBy = 'created_at',
        public readonly string $order = 'desc',
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
            travelMode: isset($params['travel_mode']) ? (int) $params['travel_mode'] : null,
            arrangementType: isset($params['arrangement_type']) ? (int) $params['arrangement_type'] : null,
            startDate: $params['start_date'] ?? $params['from_date'] ?? null,
            endDate: $params['end_date'] ?? $params['to_date'] ?? null,
            month: $params['month'] ?? null,
            companyId: isset($params['company_id']) ? (int) $params['company_id'] : null,
            hierarchyLevels: $params['hierarchy_levels'] ?? null,
            search: $params['search'] ?? '',
            perPage: isset($params['per_page']) ? (int) $params['per_page'] : 15,
            page: isset($params['page']) ? (int) $params['page'] : 1,
            orderBy: $params['order_by'] ?? 'created_at',
            order: $params['order'] ?? 'desc',
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
            || $this->travelMode !== null
            || $this->arrangementType !== null
            || $this->startDate !== null
            || $this->endDate !== null
            || $this->month !== null
            || $this->orderBy !== null
            || $this->order !== null
            || $this->search !== '';
    }

    public function toArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'employee_ids' => $this->employeeIds,
            'status' => $this->status,
            'travel_mode' => $this->travelMode,
            'arrangement_type' => $this->arrangementType,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'month' => $this->month,
            'company_id' => $this->companyId,
            'hierarchy_levels' => $this->hierarchyLevels,
            'search' => $this->search,
            'per_page' => $this->perPage,
            'page' => $this->page,
            'order_by' => $this->orderBy,
            'order' => $this->order,
        ];
    }
}
