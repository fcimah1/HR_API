<?php

declare(strict_types=1);

namespace App\DTOs\Meeting;

class MeetingFilterDTO
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $date = null,
        public readonly bool $paginate = true,
        public readonly int $perPage = 10,
        public readonly int $page = 1
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            date: $data['date'] ?? null,
            paginate: filter_var($data['paginate'] ?? true, FILTER_VALIDATE_BOOLEAN),
            perPage: (int)($data['per_page'] ?? 10),
            page: (int)($data['page'] ?? 1)
        );
    }
}
