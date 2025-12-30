<?php

namespace App\DTOs\Complaint;

use App\Models\Complaint;
use Spatie\LaravelData\Data;

class CreateComplaintDTO extends Data
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $complaintFrom,
        public readonly string $title,
        public readonly string $complaintDate,
        public readonly ?array $complaintAgainst,
        public readonly ?string $description,
        public readonly ?array $notifySendTo = null,
        public readonly int $status = Complaint::STATUS_PENDING,
    ) {}

    public static function fromRequest(array $data, int $companyId, int $complaintFrom): self
    {
        return new self(
            companyId: $companyId,
            complaintFrom: $complaintFrom,
            title: $data['title'],
            complaintDate: $data['complaint_date'] ?? now()->format('Y-m-d'),
            complaintAgainst: $data['complaint_against'] ?? null,
            description: $data['description'] ?? null,
            notifySendTo: $data['notify_send_to'] ?? null,
            status: Complaint::STATUS_PENDING,
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'complaint_from' => $this->complaintFrom,
            'title' => $this->title,
            'complaint_date' => $this->complaintDate,
            'complaint_against' => is_array($this->complaintAgainst) ? implode(',', $this->complaintAgainst) : null,
            'description' => $this->description,
            'notify_send_to' => is_array($this->notifySendTo) ? implode(',', $this->notifySendTo) : $this->notifySendTo,
            'status' => $this->status,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
