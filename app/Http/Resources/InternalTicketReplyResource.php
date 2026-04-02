<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="InternalTicketReplyResource",
 *     @OA\Property(property="ticket_reply_id", type="integer"),
 *     @OA\Property(property="ticket_id", type="integer"),
 *     @OA\Property(property="sent_by", type="integer"),
 *     @OA\Property(property="sender_name", type="string"),
 *     @OA\Property(property="assign_to", type="integer"),
 *     @OA\Property(property="assignee_name", type="string"),
 *     @OA\Property(property="reply_text", type="string"),
 *     @OA\Property(property="created_at", type="string")
 * )
 */
class InternalTicketReplyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ticket_reply_id' => $this->ticket_reply_id,
            'ticket_id' => $this->ticket_id,
            'company_id' => $this->company_id,
            'sent_by' => $this->sent_by,
            'sender_name' => $this->sender
                ? trim($this->sender->first_name . ' ' . $this->sender->last_name)
                : null,
            'assign_to' => $this->assign_to,
            'assignee_name' => $this->assignee
                ? trim($this->assignee->first_name . ' ' . $this->assignee->last_name)
                : null,
            'reply_text' => $this->reply_text,
            'created_at' => $this->created_at?->format('d-m-Y H:i:s'),
        ];
    }
}
