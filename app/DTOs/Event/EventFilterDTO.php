<?php

declare(strict_types=1);

namespace App\DTOs\Event;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

class EventFilterDTO extends Data
{
    public function __construct(
        public readonly int $companyId,
        public readonly User $requester,
        public readonly ?string $search = null,
        public readonly int $perPage = 15,
        public readonly int $page = 1,
    ) {}

    public static function fromRequest(Request $request, int $companyId, User $requester): self
    {
        return new self(
            companyId: $companyId,
            requester: $requester,
            search: $request->query('search'),
            perPage: (int) $request->query('per_page', '15'),
            page: (int) $request->query('page', '1')
        );
    }
}
