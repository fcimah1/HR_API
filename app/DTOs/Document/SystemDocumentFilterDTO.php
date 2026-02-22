<?php

declare(strict_types=1);

namespace App\DTOs\Document;

use Spatie\LaravelData\Data;

class SystemDocumentFilterDTO extends Data
{
    public function __construct(
        public readonly int $companyId,
        public readonly ?int $departmentId = null,
        public readonly ?string $search = null,
        public readonly int $perPage = 15,
        public readonly int $page = 1,
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            departmentId: isset($data['department_id']) ? (int) $data['department_id'] : null,
            search: $data['search'] ?? null,
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15,
            page: isset($data['page']) ? (int) $data['page'] : 1,
        );
    }
}
