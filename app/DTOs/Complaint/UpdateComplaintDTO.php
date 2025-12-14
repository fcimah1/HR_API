<?php

namespace App\DTOs\Complaint;

use Spatie\LaravelData\Data;

class UpdateComplaintDTO extends Data
{
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $complaintAgainst = null,
        public readonly ?string $description = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            complaintAgainst: $data['complaint_against'] ?? null,
            description: $data['description'] ?? null,
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->title !== null) {
            $data['title'] = $this->title;
        }

        if ($this->complaintAgainst !== null) {
            $data['complaint_against'] = $this->complaintAgainst;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        return $data;
    }
}
