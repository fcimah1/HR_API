<?php

declare(strict_types=1);

namespace App\DTOs\SupportTicket;

use Spatie\LaravelData\Data;

class CloseTicketDTO extends Data
{
    public function __construct(
        public readonly int $closedBy,
        public readonly ?string $ticketRemarks = null,
    ) {}

    public static function fromRequest(array $data, int $closedBy): self
    {
        return new self(
            closedBy: $closedBy,
            ticketRemarks: $data['ticket_remarks'] ?? null,
        );
    }
}
