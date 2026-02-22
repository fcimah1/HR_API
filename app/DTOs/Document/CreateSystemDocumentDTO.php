<?php

declare(strict_types=1);

namespace App\DTOs\Document;

use Spatie\LaravelData\Data;
use Illuminate\Http\UploadedFile;

class CreateSystemDocumentDTO extends Data
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $departmentId,
        public readonly string $documentName,
        public readonly string $documentType,
        public readonly UploadedFile $documentFile,
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            departmentId: (int) $data['department_id'],
            documentName: (string) $data['document_name'],
            documentType: (string) $data['document_type'],
            documentFile: $data['document_file'], // This is an UploadedFile object
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'department_id' => $this->departmentId,
            'document_name' => $this->documentName,
            'document_type' => $this->documentType,
            'created_at' => date('d-m-Y H:i:s'),
        ];
    }
}
