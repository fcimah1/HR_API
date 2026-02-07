<?php

declare(strict_types=1);

namespace App\DTOs\Polls;

class UpdatePollDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $companyId,
        public readonly string $pollTitle,
        public readonly string $pollStartDate,
        public readonly string $pollEndDate,
        public readonly int $addedBy,
        public readonly bool $isActive,
        public readonly array $questions = [],
    ) {}

    public static function fromRequest(array $data, int $id, int $companyId, int $userId): self
    {
        return new self(
            id: $id,
            companyId: $companyId,
            pollTitle: $data['poll_title'],
            pollStartDate: $data['poll_start_date'],
            pollEndDate: $data['poll_end_date'],
            addedBy: $userId,
            isActive: $data['is_active'] ?? true,
            questions: $data['questions'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'poll_title' => $this->pollTitle,
            'poll_start_date' => $this->pollStartDate,
            'poll_end_date' => $this->pollEndDate,
            'is_active' => $this->isActive,
        ];
    }
}
