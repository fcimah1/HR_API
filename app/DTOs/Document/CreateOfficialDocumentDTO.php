<?php

declare(strict_types=1);

namespace App\DTOs\Document;

use Spatie\LaravelData\Data;
use Illuminate\Http\UploadedFile;

class CreateOfficialDocumentDTO extends Data
{
    public function __construct(
        public readonly int $companyId,
        public readonly string $licenseName,
        public readonly string $documentType,
        public readonly ?string $licenseNo,
        public readonly ?string $expiryDate,
        public readonly UploadedFile $documentFile,
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            licenseName: $data['license_name'],
            documentType: $data['document_type'],
            licenseNo: $data['license_no'] ?? null,
            expiryDate: $data['expiry_date'] ?? null,
            documentFile: $data['document_file'],
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'license_name' => $this->licenseName,
            'document_type' => $this->documentType,
            'license_no' => $this->licenseNo,
            'expiry_date' => $this->expiryDate,
            'created_at' => now()->format('d-m-Y H:i:s'),
        ];
    }
}
