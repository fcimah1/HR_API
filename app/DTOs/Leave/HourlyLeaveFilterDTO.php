<?php

namespace App\DTOs\Leave;

use App\Enums\NumericalStatusEnum;

class HourlyLeaveFilterDTO
{
    public function __construct(
        public readonly ?int $companyId = null,
        public readonly ?int $employeeId = null,
        public readonly ?array $employeeIds = null,
        public readonly ?array $excludedLeaveTypeIds = null, // Exclude restricted leave types
        public readonly ?int $status = null, // 1 for pending, 2 for approved, 3 for rejected
        public readonly ?int $leaveTypeId = null,
        public readonly ?string $clockInM = null,
        public readonly ?string $clockOutM = null,
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly ?string $search = null,
        public readonly int $perPage = 15,
        public readonly int $page = 1,
        public readonly string $sortBy = 'created_at',
        public readonly string $sortDirection = 'desc'
    ) {}

    public static function fromRequest(array $data): self
    {
        // Handle status conversion properly
        $status = null;
        if (array_key_exists('status', $data) && $data['status'] !== null) {
            if ($data['status'] === 'approved' || $data['status'] === NumericalStatusEnum::APPROVED->value) {
                $status = NumericalStatusEnum::APPROVED->value;
            } else if ($data['status'] === 'rejected' || $data['status'] === NumericalStatusEnum::REJECTED->value) {
                $status = NumericalStatusEnum::REJECTED->value;
            } else if ($data['status'] === 'pending' || $data['status'] === NumericalStatusEnum::PENDING->value) {
                $status = NumericalStatusEnum::PENDING->value;
            } else {
                $status = null;
            }
        }

        return new self(
            companyId: $data['company_id'] ?? null,
            employeeId: $data['employee_id'] ?? null,
            employeeIds: $data['employee_ids'] ?? null,
            excludedLeaveTypeIds: $data['excluded_leave_type_ids'] ?? null,
            status: $status,
            leaveTypeId: $data['leave_type_id'] ?? null,
            clockInM: $data['clock_in_m'] ?? null,
            clockOutM: $data['clock_out_m'] ?? null,
            fromDate: $data['from_date'] ?? null,
            toDate: $data['to_date'] ?? null,
            search: $data['search'] ?? null,
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15,
            page: isset($data['page']) ? (int) $data['page'] : 1,
            sortBy: $data['sort_by'] ?? 'created_at',
            sortDirection: $data['sort_direction'] ?? 'desc'
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'employee_ids' => $this->employeeIds,
            'excluded_leave_type_ids' => $this->excludedLeaveTypeIds,
            'status' => $this->status,
            'leave_type_id' => $this->leaveTypeId,
            'clock_in_m' => $this->clockInM,
            'clock_out_m' => $this->clockOutM,
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
            'search' => $this->search,
            'per_page' => $this->perPage,
            'page' => $this->page,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
        ];
    }
}
