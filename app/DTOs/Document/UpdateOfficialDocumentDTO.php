<?php

declare(strict_types=1);

namespace App\DTOs\Document;

use Spatie\LaravelData\Data;
use Illuminate\Http\UploadedFile;

class UpdateOfficialDocumentDTO extends Data
{
    public function __construct(
        public readonly ?string $licenseName = null,
        public readonly ?string $documentType = null,
        public readonly ?string $licenseNo = null,
        public readonly ?string $expiryDate = null,
        public readonly ?UploadedFile $documentFile = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            licenseName: $data['license_name'] ?? null,
            documentType: $data['document_type'] ?? null,
            licenseNo: $data['license_no'] ?? null,
            expiryDate: $data['expiry_date'] ?? null,
            documentFile: $data['document_file'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'license_name' => $this->licenseName,
            'document_type' => $this->documentType,
            'license_no' => $this->licenseNo,
            'expiry_date' => $this->expiryDate,
            'document_file' => $this->documentFile,
        ], fn($value) => !is_null($value));
    }
}
