<?php

namespace App\Repository;

use App\DTOs\Polls\CreatePollDTO;
use App\DTOs\Polls\PollFilterDTO;
use App\DTOs\Polls\UpdatePollDTO;
use App\Models\Poll;
use App\Models\PollQuestion;
use App\Models\PollVote;
use App\Repository\Interface\PollRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PollRepository implements PollRepositoryInterface
{
    public function create(CreatePollDTO $dto): Poll
    {
        return DB::transaction(function () use ($dto) {
            $poll = Poll::create($dto->toArray());

            if (!empty($dto->questions)) {
                foreach ($dto->questions as $questionData) {
                    $poll->questions()->create([
                        'company_id' => $dto->companyId,
                        'poll_question' => $questionData['poll_question'],
                        'poll_answer1' => $questionData['poll_answer1'] ?? null,
                        'poll_answer2' => $questionData['poll_answer2'] ?? null,
                        'poll_answer3' => $questionData['poll_answer3'] ?? null,
                        'poll_answer4' => $questionData['poll_answer4'] ?? null,
                        'poll_answer5' => $questionData['poll_answer5'] ?? null,
                        'notes' => $questionData['notes'] ?? null,
                    ]);
                }
            }

            return $poll->load('questions');
        });
    }

    public function update(Poll $poll, UpdatePollDTO $dto): Poll
    {
        return DB::transaction(function () use ($poll, $dto) {
            $poll->update($dto->toArray());

            if (!empty($dto->questions)) {
                $existingQuestionIds = [];
                foreach ($dto->questions as $questionData) {
                    $questionFields = [
                        'company_id' => $dto->companyId,
                        'poll_question' => $questionData['poll_question'],
                        'poll_answer1' => $questionData['poll_answer1'] ?? null,
                        'poll_answer2' => $questionData['poll_answer2'] ?? null,
                        'poll_answer3' => $questionData['poll_answer3'] ?? null,
                        'poll_answer4' => $questionData['poll_answer4'] ?? null,
                        'poll_answer5' => $questionData['poll_answer5'] ?? null,
                        'notes' => $questionData['notes'] ?? null,
                    ];

                    if (!empty($questionData['id'])) {
                        $question = $poll->questions()->find($questionData['id']);
                        if ($question) {
                            $question->update($questionFields);
                            $existingQuestionIds[] = $question->id;
                        }
                    } else {
                        $newQuestion = $poll->questions()->create($questionFields);
                        $existingQuestionIds[] = $newQuestion->id;
                    }
                }

                // Optionally delete questions not in the update list? 
                // For now, we'll keep it simple and only update or add.
            }

            return $poll->load('questions');
        });
    }

    public function getPaginated(PollFilterDTO $filters): array
    {
        $query = Poll::query()->with('questions')->forCompany($filters->companyId);

        if ($filters->status === 'active') {
            $query->active();
        } elseif ($filters->status === 'expired') {
            $query->whereDate('poll_end_date', '<', now());
        } elseif ($filters->status === 'upcoming') {
            $query->whereDate('poll_start_date', '>', now());
        }

        if ($filters->search) {
            $query->where('poll_title', 'like', "%{$filters->search}%")
                ->orWhereHas('questions', function ($q) use ($filters) {
                    $q->where('poll_question', 'like', "%{$filters->search}%");
                });
        }

        $query->orderBy('created_at', 'desc');

        $polls = $query->paginate($filters->perPage, ['*'], 'page', $filters->page);

        return [
            'data' => $polls->items(),
            'pagination' => [
                'current_page' => $polls->currentPage(),
                'last_page' => $polls->lastPage(),
                'per_page' => $polls->perPage(),
                'total' => $polls->total(),
            ],
        ];
    }

    public function findById(int $id, int $companyId): ?Poll
    {
        return Poll::with(['questions', 'creator'])
            ->forCompany($companyId)
            ->find($id);
    }

    public function delete(Poll $poll): void
    {
        DB::transaction(function () use ($poll) {
            $poll->votes()->delete();
            $poll->questions()->delete();
            $poll->delete();
        });
    }

    public function vote(int $pollId, int $userId, array $votes, int $companyId): void
    {
        DB::transaction(function () use ($pollId, $userId, $votes, $companyId) {
            foreach ($votes as $voteData) {
                // $voteData = ['question_id' => 123, 'answer' => 'Yes']
                $questionId = $voteData['question_id'];
                $answer = $voteData['answer'];

                $question = PollQuestion::where('poll_ref_id', $pollId)->find($questionId);
                if (!$question) {
                    continue;
                }

                // Resolve answer text to index (1-5) or store directly if it's already a valid index
                $storedAnswer = $answer;
                if (!is_numeric($answer) || (int) $answer < 1 || (int) $answer > 5) {
                    for ($i = 1; $i <= 5; $i++) {
                        if ($question->{"poll_answer$i"} === $answer) {
                            $storedAnswer = (string) $i;
                            break;
                        }
                    }
                }

                PollVote::create([
                    'company_id' => $companyId,
                    'poll_id' => $pollId,
                    'poll_question_id' => $questionId,
                    'poll_answer' => $storedAnswer,
                    'user_id' => $userId,
                    'created_at' => now(),
                ]);
            }
        });
    }

    public function hasVoted(int $pollId, int $userId): bool
    {
        return PollVote::where('poll_id', $pollId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function getVoteStats(int $pollId): array
    {
        return PollVote::where('poll_id', $pollId)->get()->groupBy('poll_question_id')->toArray();
    }
}
