<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PollResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'poll_id' => $this->poll_id,
            'company_id' => $this->company_id,
            'poll_title' => $this->poll_title,
            'poll_start_date' => $this->poll_start_date,
            'poll_end_date' => $this->poll_end_date,
            'is_active' => (bool) $this->is_active,
            'added_by' => $this->added_by,
            'created_at' => $this->created_at,

            'creator' => $this->when($this->relationLoaded('creator'), function () {
                return [
                    'user_id' => $this->creator->user_id,
                    'full_name' => $this->creator->full_name,
                ];
            }),

            'questions' => PollQuestionResource::collection($this->whenLoaded('questions')),

            'has_voted' => $this->when(isset($this->has_voted), $this->has_voted),
        ];
    }
}
