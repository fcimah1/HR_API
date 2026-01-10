<?php

declare(strict_types=1);

namespace App\DTOs\SupportTicket;

use Spatie\LaravelData\Data;

class UpdateTicketDTO extends Data
{
    public function __construct(
        public readonly ?string $subject = null,
        public readonly ?int $categoryId = null,
        public readonly ?int $ticketPriority = null,
        public readonly ?string $description = null,
        public readonly ?string $ticketRemarks = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            subject: $data['subject'] ?? null,
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            ticketPriority: isset($data['ticket_priority']) ? (int) $data['ticket_priority'] : null,
            description: $data['description'] ?? null,
            ticketRemarks: $data['ticket_remarks'] ?? null,
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->subject !== null) {
            $data['subject'] = $this->subject;
        }

        if ($this->categoryId !== null) {
            $data['category_id'] = $this->categoryId;
        }

        if ($this->ticketPriority !== null) {
            $data['ticket_priority'] = $this->ticketPriority;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->ticketRemarks !== null) {
            $data['ticket_remarks'] = $this->ticketRemarks;
        }

        return $data;
    }
}
