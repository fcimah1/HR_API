<?php

namespace App\DTOs\Polls;

use Illuminate\Support\Carbon;

class CreatePollDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly string $pollTitle,
        public readonly string $pollStartDate,
        public readonly string $pollEndDate,
        public readonly int $addedBy,
        public readonly bool $isActive,
        public readonly array $questions = [],
    ) {}

    public static function fromRequest(array $data, int $companyId, int $userId): self
    {
        return new self(
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
            'added_by' => $this->addedBy,
            'is_active' => $this->isActive,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
