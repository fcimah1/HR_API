<?php

declare(strict_types=1);

namespace App\DTOs\InternalHelpdesk;

class UpdateInternalTicketDTO
{
    public function __construct(
        public readonly ?string $subject,
        public readonly ?string $description,
        public readonly ?int $priority,
        public readonly ?int $employeeId,
        public readonly ?int $departmentId,
        public readonly ?string $ticketRemarks,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            subject: $data['subject'] ?? null,
            description: $data['description'] ?? null,
            priority: $data['ticket_priority'] ?? null,
            employeeId: $data['employee_id'] ?? null,
            departmentId: $data['department_id'] ?? null,
            ticketRemarks: $data['ticket_remarks'] ?? null,
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->subject !== null) {
            $data['subject'] = $this->subject;
        }
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        if ($this->priority !== null) {
            $data['ticket_priority'] = $this->priority;
        }
        if ($this->employeeId !== null) {
            $data['employee_id'] = $this->employeeId;
        }
        if ($this->departmentId !== null) {
            $data['department_id'] = $this->departmentId;
        }
        if ($this->ticketRemarks !== null) {
            $data['ticket_remarks'] = $this->ticketRemarks;
        }

        return $data;
    }
}
