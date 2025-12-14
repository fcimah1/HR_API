<?php

namespace App\DTOs\Suggestion;

use Spatie\LaravelData\Data;

class CreateSuggestionCommentDTO extends Data
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $suggestionId,
        public readonly int $employeeId,
        public readonly string $comment,
    ) {}

    public static function fromRequest(array $data, int $companyId, int $suggestionId, int $employeeId): self
    {
        return new self(
            companyId: $companyId,
            suggestionId: $suggestionId,
            employeeId: $employeeId,
            comment: $data['comment'],
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'suggestion_id' => $this->suggestionId,
            'employee_id' => $this->employeeId,
            'suggestion_comment' => $this->comment,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
