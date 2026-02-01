<?php

namespace App\DTOs\OfficeShift;

class OfficeShiftFilterDTO
{
    public function __construct(
        public int $companyId,
        public ?string $search = null,
        public int $page = 1,
        public int $limit = 20
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            companyId: (int) ($data['company_id'] ?? 0),
            search: $data['search'] ?? null,
            page: isset($data['page']) ? (int) $data['page'] : 1,
            limit: isset($data['limit']) ? (int) $data['limit'] : 20
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'search' => $this->search,
            'page' => $this->page,
            'limit' => $this->limit,
        ];
    }
}
