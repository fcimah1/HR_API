<?php

declare(strict_types=1);

namespace App\DTOs\SupportTicket;

use Spatie\LaravelData\Data;

class TicketFilterDTO extends Data
{
    public function __construct(
        public ?int $companyId = null,
        public ?int $createdBy = null,
        public ?int $status = null,
        public ?int $categoryId = null,
        public ?int $priority = null,
        public ?string $search = null,
        public ?string $fromDate = null,
        public ?string $toDate = null,
        public int $page = 1,
        public int $perPage = 15,
        public bool $isSuperUser = false,
    ) {}

    public static function fromRequest(array $data, ?int $companyId = null, ?int $createdBy = null, bool $isSuperUser = false): self
    {
        return new self(
            companyId: $companyId,
            createdBy: $createdBy,
            status: isset($data['status']) ? (int) $data['status'] : null,
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            priority: isset($data['priority']) ? (int) $data['priority'] : null,
            search: $data['search'] ?? null,
            fromDate: $data['from_date'] ?? null,
            toDate: $data['to_date'] ?? null,
            page: (int) ($data['page'] ?? 1),
            perPage: min((int) ($data['per_page'] ?? 15), 100),
            isSuperUser: $isSuperUser,
        );
    }
}
