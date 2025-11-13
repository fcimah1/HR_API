<?php

namespace App\DTOs\Leave;

class LeaveAdjustmentFilterDTO
{
    public function __construct(
        public readonly ?string $companyName = null,
        public readonly ?int $employeeId = null,
        public readonly ?int $status = null,
        public readonly ?int $leaveTypeId = null,
        public readonly int $perPage = 15,
        public readonly int $page = 1,
        public readonly string $sortBy = 'created_at',
        public readonly string $sortDirection = 'desc'
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            companyName: $data['company_name'] ?? null,
            employeeId: $data['employee_id'] ?? null,
            status: isset($data['status']) ? (int) $data['status'] : null,
            leaveTypeId: $data['leave_type_id'] ?? null,
            perPage: (int) ($data['per_page'] ?? 15),
            page: (int) ($data['page'] ?? 1),
            sortBy: $data['sort_by'] ?? 'created_at',
            sortDirection: $data['sort_direction'] ?? 'desc'
        );
    }

    public function toArray(): array
    {
        return [
            'company_name' => $this->companyName,
            'employee_id' => $this->employeeId,
            'status' => $this->status,
            'leave_type_id' => $this->leaveTypeId,
            'per_page' => $this->perPage,
            'page' => $this->page,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
        ];
    }
}
