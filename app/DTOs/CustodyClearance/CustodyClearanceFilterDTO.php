<?php

namespace App\DTOs\CustodyClearance;

class CustodyClearanceFilterDTO
{
    public function __construct(
        public readonly ?int $companyId = null,
        public readonly ?int $employeeId = null,
        public readonly ?array $employeeIds = null,
        public readonly ?string $status = null, // pending/approved/rejected
        public readonly ?string $clearanceType = null, // resignation/termination/transfer/other
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly ?string $search = null,
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            companyId: $data['company_id'] ?? null,
            employeeId: isset($data['employee_id']) ? (int) $data['employee_id'] : null,
            employeeIds: $data['employee_ids'] ?? null,
            status: $data['status'] ?? null,
            clearanceType: $data['clearance_type'] ?? null,
            fromDate: $data['from_date'] ?? null,
            toDate: $data['to_date'] ?? null,
            search: $data['search'] ?? null,
            page: isset($data['page']) ? (int) $data['page'] : 1,
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15,
        );
    }
}
