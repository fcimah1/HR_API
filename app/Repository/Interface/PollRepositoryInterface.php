<?php

namespace App\Repository\Interface;

use App\DTOs\Polls\CreatePollDTO;
use App\DTOs\Polls\PollFilterDTO;
use App\DTOs\Polls\UpdatePollDTO;
use App\Models\Poll;

interface PollRepositoryInterface
{
    public function create(CreatePollDTO $dto): Poll;
    public function update(Poll $poll, UpdatePollDTO $dto): Poll;
    public function getPaginated(PollFilterDTO $filters): array;
    public function findById(int $id, int $companyId): ?Poll;
    public function delete(Poll $poll): void;
    public function vote(int $pollId, int $userId, array $votes, int $companyId): void;
    public function hasVoted(int $pollId, int $userId): bool;
    public function getVoteStats(int $pollId): array;
}
