<?php

declare(strict_types=1);

namespace App\DTOs\Document;

use Spatie\LaravelData\Data;

class UpdateSignatureDocumentDTO extends Data
{
    public function __construct(
        public readonly ?string $documentName = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            documentName: $data['document_name'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'document_name' => $this->documentName,
        ], fn($value) => !is_null($value));
    }
}
