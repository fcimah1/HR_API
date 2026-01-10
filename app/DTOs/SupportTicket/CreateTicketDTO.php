<?php

declare(strict_types=1);

namespace App\DTOs\SupportTicket;

use App\Enums\TicketStatusEnum;
use App\Models\SupportTicket;
use Spatie\LaravelData\Data;

class CreateTicketDTO extends Data
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $createdBy,
        public readonly string $subject,
        public readonly int $categoryId,
        public readonly int $ticketPriority,
        public readonly ?string $description = null,
        public readonly ?string $ticketRemarks = null,
        public readonly int $ticketStatus = SupportTicket::STATUS_OPEN,
    ) {}

    public static function fromRequest(array $data, int $companyId, int $createdBy): self
    {
        return new self(
            companyId: $companyId,
            createdBy: $createdBy,
            subject: $data['subject'],
            categoryId: (int) $data['category_id'],
            ticketPriority: (int) ($data['ticket_priority'] ?? 3), // افتراضي: متوسط
            description: $data['description'] ?? null,
            ticketRemarks: $data['ticket_remarks'] ?? null,
            ticketStatus: SupportTicket::STATUS_OPEN,
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'created_by' => $this->createdBy,
            'ticket_code' => SupportTicket::generateTicketCode(),
            'subject' => $this->subject,
            'category_id' => $this->categoryId,
            'ticket_priority' => $this->ticketPriority,
            'description' => $this->description,
            'ticket_remarks' => $this->ticketRemarks,
            'ticket_status' => $this->ticketStatus,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
