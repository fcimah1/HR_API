<?php

declare(strict_types=1);

namespace App\DTOs\EndOfService;

class EndOfServiceFilterDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly ?int $employeeId = null,
        public readonly ?string $terminationType = null,
        public readonly ?bool $isApproved = null,
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly ?string $search = null,
        public readonly bool $paginate = true,
        public readonly int $perPage = 10,
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            employeeId: isset($data['employee_id']) ? (int)$data['employee_id'] : null,
            terminationType: $data['termination_type'] ?? null,
            isApproved: isset($data['is_approved']) ? filter_var($data['is_approved'], FILTER_VALIDATE_BOOLEAN) : null,
            fromDate: $data['from_date'] ?? null,
            toDate: $data['to_date'] ?? null,
            search: $data['search'] ?? null,
            paginate: filter_var($data['paginate'] ?? true, FILTER_VALIDATE_BOOLEAN),
            perPage: isset($data['per_page']) ? (int)$data['per_page'] : 10,
        );
    }
}
