<?php

namespace App\DTOs\Polls;

use Illuminate\Http\Request;

class PollFilterDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly ?string $status = null, // active, expired, upcoming
        public readonly ?string $search = null,
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            status: $data['status'] ?? null,
            search: $data['search'] ?? null,
            page: (int) ($data['page'] ?? 1),
            perPage: (int) ($data['per_page'] ?? 15),
        );
    }
}
