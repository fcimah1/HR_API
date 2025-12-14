<?php

namespace App\DTOs\Suggestion;

use Spatie\LaravelData\Data;

class CreateSuggestionDTO extends Data
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $addedBy,
        public readonly string $title,
        public readonly string $description,
        public readonly ?string $attachment = null,
    ) {}

    public static function fromRequest(array $data, int $companyId, int $addedBy): self
    {
        return new self(
            companyId: $companyId,
            addedBy: $addedBy,
            title: $data['title'],
            description: $data['description'],
            attachment: $data['attachment'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'added_by' => $this->addedBy,
            'title' => $this->title,
            'description' => $this->description,
            'attachment' => $this->attachment,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
