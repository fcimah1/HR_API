<?php

namespace App\DTOs\CustodyClearance;

class CustodyFilterDTO
{
    public function __construct(
        public readonly ?int $companyId = null,
        public readonly ?int $employeeId = null,
        public readonly ?array $employeeIds = null,
        public readonly ?string $search = null,
        public readonly ?string $status = null, // working/damaged/disposed
        public readonly bool $paginate = true,
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            companyId: $data['company_id'] ?? null,
            employeeId: isset($data['employee_id']) ? (int) $data['employee_id'] : null,
            employeeIds: $data['employee_ids'] ?? null,
            search: $data['search'] ?? null,
            status: $data['status'] ?? null,
            paginate: filter_var($data['paginate'] ?? true, FILTER_VALIDATE_BOOLEAN),
            page: isset($data['page']) ? (int) $data['page'] : 1,
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15,
        );
    }
}
