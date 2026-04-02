<?php

namespace App\DTOs\Termination;

use Illuminate\Http\UploadedFile;

class CreateTerminationDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $employeeId,
        public readonly string $noticeDate,
        public readonly string $terminationDate,
        public readonly string $reason,
        public readonly int $addedBy,
        public readonly ?UploadedFile $documentFile = null,
        public readonly int $isSigned = 0,
        public readonly int $status = 0,
    ) {}

    public static function fromRequest(array $data, int $companyId, int $addedBy): self
    {
        return new self(
            companyId: $companyId,
            employeeId: (int) $data['employee_id'],
            noticeDate: $data['notice_date'],
            terminationDate: $data['termination_date'],
            reason: $data['reason'],
            addedBy: $addedBy,
            documentFile: $data['document_file'] ?? null,
            isSigned: $data['is_signed'] ?? 0,
            status: $data['status'] ?? 0,
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'notice_date' => $this->noticeDate,
            'termination_date' => $this->terminationDate,
            'reason' => $this->reason,
            'added_by' => $this->addedBy,
            'status' => $this->status,
            'is_signed' => $this->isSigned,
        ];
    }
}
