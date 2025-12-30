<?php

namespace App\DTOs\Resignation;

use App\Models\Resignation;
use Spatie\LaravelData\Data;

class CreateResignationDTO extends Data
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $employeeId,
        public readonly string $noticeDate,
        public readonly string $resignationDate,
        public readonly string $reason,
        public readonly int $addedBy,
        public readonly ?string $documentFile = null,
        public readonly int $isSigned = 0,
        public readonly ?array $notifySendTo = [],
        public readonly int $status = Resignation::STATUS_PENDING,
    ) {}

    public static function fromRequest(array $data, int $companyId, int $employeeId, int $addedBy): self
    {
        return new self(
            companyId: $companyId,
            employeeId: $employeeId,
            noticeDate: $data['notice_date'],
            resignationDate: $data['resignation_date'],
            reason: $data['reason'],
            addedBy: $addedBy,
            documentFile: $data['document_file'] ?? null,
            isSigned: $data['is_signed'] ?? 0,
            notifySendTo: $data['notify_send_to'] ?? [],
            status: Resignation::STATUS_PENDING,
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'notice_date' => $this->noticeDate,
            'resignation_date' => $this->resignationDate,
            'reason' => $this->reason,
            'added_by' => $this->addedBy,
            'document_file' => $this->documentFile,
            'notify_send_to' => is_array($this->notifySendTo) ? implode(',', $this->notifySendTo) : $this->notifySendTo,
            'is_signed' => $this->isSigned,
            'status' => $this->status,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
