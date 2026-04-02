<?php

namespace App\DTOs\Polls;

use Illuminate\Http\Request;

class VotePollDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly int $companyId,
        public readonly int $pollId,
        public readonly array $votes, // Array of ['question_id' => x, 'answer' => 'answer_text']
    ) {}

    public static function fromRequest(array $data, int $userId, int $companyId, int $pollId): self
    {
        return new self(
            userId: $userId,
            companyId: $companyId,
            pollId: $pollId,
            votes: $data['votes'],
        );
    }
}
