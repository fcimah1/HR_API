<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PollQuestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'poll_question' => $this->poll_question,
            'poll_answer1' => $this->poll_answer1,
            'poll_answer2' => $this->poll_answer2,
            'poll_answer3' => $this->poll_answer3,
            'poll_answer4' => $this->poll_answer4,
            'poll_answer5' => $this->poll_answer5,
            'notes' => $this->notes,
            'stats' => $this->when(isset($this->stats), $this->stats),
        ];
    }
}
