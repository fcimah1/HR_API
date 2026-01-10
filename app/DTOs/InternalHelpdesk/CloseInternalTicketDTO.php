<?php

declare(strict_types=1);

namespace App\DTOs\InternalHelpdesk;

class CloseInternalTicketDTO
{
    public function __construct(
        public readonly int $closedBy,
        public readonly ?string $ticketRemarks,
    ) {}

    public static function fromRequest(array $data, int $closedBy): self
    {
        return new self(
            closedBy: $closedBy,
            ticketRemarks: $data['ticket_remarks'] ?? null,
        );
    }
}
