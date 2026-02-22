<?php

declare(strict_types=1);

namespace App\DTOs\Document;

use Spatie\LaravelData\Data;

class UpdateSystemDocumentDTO extends Data
{
    public function __construct(
        public readonly ?int $departmentId = null,
        public readonly ?string $documentName = null,
        public readonly ?string $documentType = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            departmentId: isset($data['department_id']) ? (int) $data['department_id'] : null,
            documentName: $data['document_name'] ?? null,
            documentType: $data['document_type'] ?? null,
        );
    }

    public function toArray(): array
    {
        $data = [];
        if ($this->departmentId !== null) $data['department_id'] = $this->departmentId;
        if ($this->documentName !== null) $data['document_name'] = $this->documentName;
        if ($this->documentType !== null) $data['document_type'] = $this->documentType;

        return $data;
    }
}
