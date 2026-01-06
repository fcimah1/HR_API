<?php

declare(strict_types=1);

namespace App\DTOs\SupportTicket;

use Spatie\LaravelData\Data;

class CreateReplyDTO extends Data
{
    public function __construct(
        public readonly int $ticketId,
        public readonly int $companyId,
        public readonly int $sentBy,
        public readonly int $assignTo,
        public readonly string $replyText,
    ) {}

    public static function fromRequest(array $data, int $ticketId, int $companyId, int $sentBy, int $assignTo): self
    {
        return new self(
            ticketId: $ticketId,
            companyId: $companyId,
            sentBy: $sentBy,
            assignTo: $assignTo,
            replyText: $data['reply_text'],
        );
    }

    public function toArray(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'company_id' => $this->companyId,
            'sent_by' => $this->sentBy,
            'assign_to' => $this->assignTo,
            'reply_text' => $this->replyText,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
