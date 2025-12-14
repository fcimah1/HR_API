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
        public readonly string $complaintAgainst,
        public readonly string $description,
        public readonly int $status = Complaint::STATUS_PENDING,
    ) {}

    public static function fromRequest(array $data, int $companyId, int $complaintFrom): self
    {
        return new self(
            companyId: $companyId,
            complaintFrom: $complaintFrom,
            title: $data['title'],
            complaintDate: $data['complaint_date'] ?? now()->format('Y-m-d'),
            complaintAgainst: $data['complaint_against'],
            description: $data['description'],
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
            'complaint_against' => $this->complaintAgainst,
            'description' => $this->description,
            'status' => $this->status,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
