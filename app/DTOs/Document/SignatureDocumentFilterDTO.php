<?php

declare(strict_types=1);

namespace App\DTOs\Document;

use Spatie\LaravelData\Data;

class SignatureDocumentFilterDTO extends Data
{
    public function __construct(
        public readonly int $companyId,
        public readonly ?string $search = null,
        public readonly int $perPage = 15,
        public readonly int $page = 1,
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            search: $data['search'] ?? null,
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15,
            page: isset($data['page']) ? (int) $data['page'] : 1,
        );
    }
}
