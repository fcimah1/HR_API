<?php

namespace App\DTOs\Complaint;

use Spatie\LaravelData\Data;

class UpdateComplaintDTO extends Data
{
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $complaintDate = null,
        public readonly ?string $description = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            complaintDate: $data['complaint_date'] ?? null,
            description: $data['description'] ?? null,
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->title !== null) {
            $data['title'] = $this->title;
        }

        if ($this->complaintDate !== null) {
            $data['complaint_date'] = $this->complaintDate;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        return $data;
    }
}
