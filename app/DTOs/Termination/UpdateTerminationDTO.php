<?php

namespace App\DTOs\Termination;

class UpdateTerminationDTO
{
    public function __construct(
        public readonly ?string $noticeDate = null,
        public readonly ?string $terminationDate = null,
        public readonly ?string $reason = null,
        public readonly ?int $status = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        $statusValue = null;
        if (isset($data['status'])) {
            foreach (\App\Enums\NumericalStatusEnum::cases() as $case) {
                if (ucfirst(strtolower($case->name)) === $data['status']) {
                    $statusValue = $case->value;
                    break;
                }
            }
        }

        return new self(
            noticeDate: $data['notice_date'] ?? null,
            terminationDate: $data['termination_date'] ?? null,
            reason: $data['reason'] ?? null,
            status: $statusValue
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'notice_date' => $this->noticeDate,
            'termination_date' => $this->terminationDate,
            'reason' => $this->reason,
            'status' => $this->status,
        ], fn($value) => !is_null($value));
    }
}
