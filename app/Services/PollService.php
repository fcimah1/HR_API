<?php

namespace App\Services;

use App\DTOs\Polls\CreatePollDTO;
use App\DTOs\Polls\PollFilterDTO;
use App\DTOs\Polls\UpdatePollDTO;
use App\DTOs\Polls\VotePollDTO;
use App\Models\Poll;
use App\Repository\Interface\PollRepositoryInterface;
use Exception;

class PollService
{
    public function __construct(
        protected PollRepositoryInterface $pollRepository
    ) {}

    /**
     * Create a new poll with optional sub-questions.
     */
    public function createPoll(CreatePollDTO $dto): \App\Models\Poll
    {
        return $this->pollRepository->create($dto);
    }

    /**
     * Get paginated polls based on filters.
     */
    public function getPaginatedPolls(PollFilterDTO $filters): array
    {
        return $this->pollRepository->getPaginated($filters);
    }

    /**
     * Get poll details by ID, including questions and vote stats.
     */
    public function getPollById(int $pollId, int $companyId, int $userId): \App\Models\Poll
    {
        $poll = $this->pollRepository->findById($pollId, $companyId);

        if (!$poll) {
            throw new Exception('الاستبيان غير موجود');
        }

        // Check if user voted
        $hasVoted = $this->pollRepository->hasVoted($pollId, $userId);

        // Get raw vote stats
        // Grouped by question ID
        // [ 0 => [votes...], 15 => [votes...] ]
        // OR we can simple query votes again as in the repo or use the method
        // Re-using the logic from before but adapted to repository if needed.
        // For now, I will keep the calculation logic here or move intricate stats logic to repo?
        // The repo `getVoteStats` returns all votes. Let's use that or keep logic here.
        // Actually, to avoid N+1, I should load votes efficiently. 
        // Let's stick to the previous logic but using the repository method if it helps, 
        // or just rely on the relationship loaded in findById if feasible. 
        // `findById` loads `questions`.

        // Calculate stats for Questions
        $questionsWithStats = $poll->questions->map(function ($question) use ($pollId) {
            $stats = $this->calculateStats($pollId, $question->id, $question);
            $question->stats = $stats;
            return $question;
        });

        // $poll->stats = $mainStats; // No main stats anymore
        $poll->setRelation('questions', $questionsWithStats);
        $poll->has_voted = $hasVoted;

        return $poll;
    }

    /**
     * Calculate vote statistics for a question.
     */
    private function calculateStats(int $pollId, int $questionId, $questionModel): array
    {
        // We can optimization this by fetching all votes for the poll once.
        // But for now, keeping it simple as per original implementation.
        // Ideally repository should provide a method to get votes for a question.
        // ... (Using same logic, just applied generically to any question ID)
        return $this->calculateStatsFromModel($pollId, $questionId, $questionModel);
    }

    private function calculateStatsFromModel(int $pollId, int $questionId, $questionModel): array
    {
        $votes = \App\Models\PollVote::where('poll_id', $pollId)
            ->where('poll_question_id', $questionId)
            ->get();

        $totalVotes = $votes->count();
        $results = [];

        $voteCounts = $votes->groupBy('poll_answer')->map->count();

        for ($i = 1; $i <= 5; $i++) {
            $answerText = $questionModel->{"poll_answer$i"};
            if ($answerText) {
                // Look up count by index $i (stored as string in DB)
                $count = $voteCounts->get((string) $i, 0);

                // Fallback: check if some old votes still have the text version
                if ($count === 0 && $voteCounts->has($answerText)) {
                    $count = $voteCounts->get($answerText);
                }

                $percentage = $totalVotes > 0 ? round(($count / $totalVotes) * 100, 1) : 0;
                $results[] = [
                    'answer_index' => $i,
                    'answer' => $answerText,
                    'count' => $count,
                    'percentage' => $percentage,
                ];
            }
        }

        return [
            'total_votes' => $totalVotes,
            'results' => $results,
        ];
    }

    /**
     * Submit a vote.
     */
    public function vote(VotePollDTO $dto): void
    {
        $poll = $this->pollRepository->findById($dto->pollId, $dto->companyId);

        if (!$poll) {
            throw new Exception('الاستبيان غير موجود');
        }

        if (!$poll->is_active) {
            throw new Exception('هذا الاستبيان غير مفعل');
        }

        $now = now();
        if ($now < $poll->poll_start_date) {
            throw new Exception('هذا الاستبيان لم يبدأ بعد');
        }
        if ($now > $poll->poll_end_date) {
            throw new Exception('هذا الاستبيان منتهي');
        }

        // Check if already voted
        if ($this->pollRepository->hasVoted($dto->pollId, $dto->userId)) {
            throw new Exception('لقد قمت بالتصويت مسبقاً على هذا الاستبيان');
        }

        $this->pollRepository->vote($dto->pollId, $dto->userId, $dto->votes, $dto->companyId);
    }

    /**
     * Delete a poll.
     */
    public function deletePoll(int $pollId, int $companyId): void
    {
        $poll = $this->pollRepository->findById($pollId, $companyId);

        if (!$poll) {
            throw new Exception('الاستبيان غير موجود');
        }

        $this->pollRepository->delete($poll);
    }

    /**
     * Update an existing poll.
     */
    public function updatePoll(UpdatePollDTO $dto): Poll
    {
        $poll = $this->pollRepository->findById($dto->id, $dto->companyId);

        if (!$poll) {
            throw new Exception('الاستبيان غير موجود');
        }

        return $this->pollRepository->update($poll, $dto);
    }
}
