<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="TicketReplyResource",
 *     title="Ticket Reply Resource",
 *     description="رد على تذكرة دعم فني",
 *     @OA\Property(property="ticket_reply_id", type="integer", example=1),
 *     @OA\Property(property="ticket_id", type="integer", example=1),
 *     @OA\Property(property="sent_by", type="integer", example=24),
 *     @OA\Property(property="sender_name", type="string", example="أحمد محمد"),
 *     @OA\Property(property="sender_type", type="string", example="employee"),
 *     @OA\Property(property="is_super_user", type="boolean", example=false),
 *     @OA\Property(property="assign_to", type="integer", example=1),
 *     @OA\Property(property="assignee_name", type="string", example="الدعم الفني"),
 *     @OA\Property(property="reply_text", type="string", example="شكراً لتواصلكم"),
 *     @OA\Property(property="created_at", type="string", format="datetime"),
 *     @OA\Property(property="formatted_created_at", type="string", example="2026-01-06 10:30:00"),
 *     @OA\Property(property="time_ago", type="string", example="منذ 5 دقائق")
 * )
 */
class TicketReplyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ticket_reply_id' => $this->ticket_reply_id,
            'ticket_id' => $this->ticket_id,
            'sent_by' => $this->sent_by,
            'sender_name' => $this->when(
                $this->relationLoaded('sender'),
                fn() => $this->sender?->full_name ?? 'غير معروف',
                fn() => $this->sender_name ?? 'غير معروف'
            ),
            'sender_type' => $this->when(
                $this->relationLoaded('sender'),
                fn() => $this->sender?->user_type ?? null
            ),
            'is_super_user' => $this->when(
                $this->relationLoaded('sender'),
                fn() => $this->sender?->user_type === 'super_user'
            ),
            'assign_to' => $this->assign_to,
            'assignee_name' => $this->when(
                $this->relationLoaded('assignee'),
                fn() => $this->assignee?->full_name ?? 'غير معروف',
                fn() => $this->assignee_name ?? 'غير معروف'
            ),
            'reply_text' => $this->reply_text,
            'created_at' => $this->created_at,
            'formatted_created_at' => $this->formatted_created_at,
            'time_ago' => $this->time_ago,
        ];
    }
}
