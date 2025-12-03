<?php

namespace App\DTOs\Travel;

class TravelRequestFilterDTO
{
    public function __construct(
        public readonly ?int $employeeId = null,
        public readonly ?array $employeeIds = null,
        public readonly ?int $status = null, // 0=pending, 1=approved, 2=rejected
        public readonly ?int $travelReason = null,
        public readonly ?string $travelType = null, // 1=local, 2=foreign
        public readonly ?int $travelWay = null, // 1-bus, 2=train, 3-plane 4-taxi 5-rental_car
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly ?string $month = null, // Format: YYYY-MM
        public readonly ?int $companyId = null,
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
            travelReason: isset($params['travel_reason']) ? (int) $params['travel_reason'] : null,
            travelType: isset($params['travel_type']) ? (int) $params['travel_type'] : null,
            travelWay: isset($params['travel_way']) ? (int) $params['travel_way'] : null,
            fromDate: $params['from_date'] ?? null,
            toDate: $params['to_date'] ?? null,
            month: $params['month'] ?? null,
            companyId: isset($params['company_id']) ? (int) $params['company_id'] : null,
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
            || $this->travelReason !== null
            || $this->travelType !== null
            || $this->travelWay !== null
            || $this->fromDate !== null
            || $this->toDate !== null
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
            'travel_reason' => $this->travelReason,
            'travel_type' => $this->travelType,
            'travel_way' => $this->travelWay,
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
            'month' => $this->month,
            'company_id' => $this->companyId,
            'search' => $this->search,
            'per_page' => $this->perPage,
            'page' => $this->page,
            'order_by' => $this->orderBy,
            'order' => $this->order,
        ];
    }
}
