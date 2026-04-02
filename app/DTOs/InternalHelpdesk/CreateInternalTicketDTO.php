<?php

declare(strict_types=1);

namespace App\DTOs\InternalHelpdesk;

use App\Enums\TicketStatusEnum;

class CreateInternalTicketDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly string $subject,
        public readonly string $description,
        public readonly int $priority,
        public readonly int $employeeId,
        public readonly int $departmentId,
        public readonly int $createdBy,
        public readonly string $ticketCode,
    ) {}

    public static function fromRequest(
        array $data,
        int $companyId,
        int $createdBy,
        int $employeeId,
        int $departmentId,
        string $ticketCode
    ): self {
        return new self(
            companyId: $companyId,
            subject: $data['subject'],
            description: $data['description'] ?? '',
            priority: $data['ticket_priority'] ?? 3,
            employeeId: $employeeId,
            departmentId: $departmentId,
            createdBy: $createdBy,
            ticketCode: $ticketCode,
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'ticket_code' => $this->ticketCode,
            'subject' => $this->subject,
            'description' => $this->description,
            'ticket_priority' => $this->priority,
            'employee_id' => $this->employeeId,
            'department_id' => $this->departmentId,
            'created_by' => $this->createdBy,
            'ticket_status' => TicketStatusEnum::OPEN->value,
            'created_at' => now(),
        ];
    }
}
