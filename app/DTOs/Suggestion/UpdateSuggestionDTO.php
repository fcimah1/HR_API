<?php

namespace App\DTOs\Suggestion;

use Spatie\LaravelData\Data;

class UpdateSuggestionDTO extends Data
{
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $attachment = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            description: $data['description'] ?? null,
            attachment: $data['attachment'] ?? null,
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->title !== null) {
            $data['title'] = $this->title;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->attachment !== null) {
            $data['attachment'] = $this->attachment;
        }

        return $data;
    }
}
