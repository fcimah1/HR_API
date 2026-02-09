<?php

declare(strict_types=1);

namespace App\DTOs\Visitor;

class VisitorFilterDTO
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $date = null,
        public readonly ?int $departmentId = null,
        public readonly bool $paginate = true,
        public readonly int $perPage = 10
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            date: $data['date'] ?? null,
            departmentId: isset($data['department_id']) ? (int)$data['department_id'] : null,
            paginate: filter_var($data['paginate'] ?? true, FILTER_VALIDATE_BOOLEAN),
            perPage: (int)($data['per_page'] ?? 10)
        );
    }
}
