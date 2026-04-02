<?php

declare(strict_types=1);

namespace App\DTOs\Trainer;

class TrainerFilterDTO
{
    public function __construct(
        public readonly ?int $companyId = null,
        public readonly ?string $search = null,
        public readonly ?string $email = null,
        public readonly int $perPage = 15,
        public readonly int $page = 1,
        public readonly string $sortBy = 'created_at',
        public readonly string $sortDirection = 'desc'
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            companyId: isset($data['company_id']) ? (int) $data['company_id'] : null,
            search: $data['search'] ?? null,
            email: $data['email'] ?? null,
            perPage: (int) ($data['per_page'] ?? 15),
            page: (int) ($data['page'] ?? 1),
            sortBy: $data['sort_by'] ?? 'created_at',
            sortDirection: $data['sort_direction'] ?? 'desc'
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'search' => $this->search,
            'email' => $this->email,
            'per_page' => $this->perPage,
            'page' => $this->page,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
        ];
    }
}
