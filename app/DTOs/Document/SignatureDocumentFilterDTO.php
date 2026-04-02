<?php

declare(strict_types=1);

namespace App\DTOs\Document;

use Spatie\LaravelData\Data;

class SignatureDocumentFilterDTO extends Data
{
    public function __construct(
        public readonly int $companyId,
        public readonly ?\App\Models\User $requester = null,
        public readonly ?string $search = null,
        public readonly int $perPage = 15,
        public readonly int $page = 1,
    ) {}

    public static function fromRequest(array $data, int $companyId, ?\App\Models\User $requester = null): self
    {
        return new self(
            companyId: $companyId,
            requester: $requester,
            search: $data['search'] ?? null,
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15,
            page: isset($data['page']) ? (int) $data['page'] : 1,
        );
    }
}
