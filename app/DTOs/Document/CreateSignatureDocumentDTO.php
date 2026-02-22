<?php

declare(strict_types=1);

namespace App\DTOs\Document;

use Spatie\LaravelData\Data;
use Illuminate\Http\UploadedFile;

class CreateSignatureDocumentDTO extends Data
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $folderId,
        public readonly mixed $shareWithEmployees, // string "all" or array of IDs
        public readonly string $documentName,
        public readonly int $signatureTask,
        public readonly UploadedFile $documentFile,
        public readonly ?array $staffIds = null,
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        $shareValue = $data['share_with_employees'] ?? '0';

        return new self(
            companyId: $companyId,
            folderId: $companyId,
            shareWithEmployees: $shareValue,
            documentName: $data['document_name'],
            signatureTask: (int) ($data['signature_task'] ?? 0),
            documentFile: $data['document_file'],
            staffIds: $data['staff_ids'] ?? null,
        );
    }

    public function toArray(): array
    {
        $shareValue = 0; // Default (Private/Example)

        if ($this->shareWithEmployees === 'all') {
            $shareValue = 0;
        } elseif (is_array($this->staffIds)) {
            $count = count($this->staffIds);
            if ($count === 1) {
                $shareValue = (int) $this->staffIds[0];
            } elseif ($count > 1) {
                $shareValue = 0;
            }
        }

        return [
            'company_id' => $this->companyId,
            'folder_id' => $this->folderId,
            'share_with_employees' => $shareValue,
            'document_name' => $this->documentName,
            'signature_task' => $this->signatureTask,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
}
