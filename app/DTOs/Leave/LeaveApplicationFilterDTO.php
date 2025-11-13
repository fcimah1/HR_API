<?php

namespace App\DTOs\Leave;

class LeaveApplicationFilterDTO
{
    public function __construct(
        public readonly ?string $companyName = null,
        public readonly ?int $companyId = null,
        public readonly ?int $employeeId = null,
        public readonly ?bool $status = null,
        public readonly ?int $leaveTypeId = null,
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
            // Handle string values "true"/"false" from query parameters
            if (is_string($data['status'])) {
                $status = filter_var($data['status'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            } else {
                $status = (bool) $data['status'];
            }
        }
        
        return new self(
            companyName: $data['company_name'] ?? null,
            companyId: $data['company_id'] ?? null,
            employeeId: $data['employee_id'] ?? null,
            status: $status,
            leaveTypeId: $data['leave_type_id'] ?? null,
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
            'company_name' => $this->companyName,
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'status' => $this->status,
            'leave_type_id' => $this->leaveTypeId,
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
            'per_page' => $this->perPage,
            'page' => $this->page,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
        ];
    }
}
