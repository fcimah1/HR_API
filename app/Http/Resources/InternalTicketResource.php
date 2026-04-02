<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="InternalTicketResource",
 *     @OA\Property(property="ticket_id", type="integer"),
 *     @OA\Property(property="ticket_code", type="string"),
 *     @OA\Property(property="company_id", type="integer"),
 *     @OA\Property(property="subject", type="string"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="department_id", type="integer"),
 *     @OA\Property(property="department_name", type="string"),
 *     @OA\Property(property="employee_id", type="integer"),
 *     @OA\Property(property="employee_name", type="string"),
 *     @OA\Property(property="ticket_priority", type="integer"),
 *     @OA\Property(property="priority_text", type="string"),
 *     @OA\Property(property="ticket_status", type="integer"),
 *     @OA\Property(property="status_text", type="string"),
 *     @OA\Property(property="is_open", type="boolean"),
 *     @OA\Property(property="created_by", type="integer"),
 *     @OA\Property(property="created_by_name", type="string"),
 *     @OA\Property(property="created_at", type="string")
 * )
 */
class InternalTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ticket_id' => $this->ticket_id,
            'ticket_code' => $this->ticket_code,
            'company_id' => $this->company_id,
            'subject' => $this->subject,
            'description' => $this->description,
            'department_id' => $this->department_id,
            'department_name' => $this->department?->department_name ?? null,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->assignedEmployee
                ? trim($this->assignedEmployee->first_name . ' ' . $this->assignedEmployee->last_name)
                : null,
            'ticket_priority' => $this->ticket_priority,
            'priority_text' => $this->priority_text,
            'priority_text_en' => $this->priority_text_en,
            'priority_color' => $this->priority_color,
            'ticket_status' => $this->ticket_status,
            'status_text' => $this->status_text,
            'status_text_en' => $this->status_text_en,
            'is_open' => $this->isOpen(),
            'ticket_remarks' => $this->ticket_remarks,
            'created_by' => $this->created_by,
            'created_by_name' => $this->creator
                ? trim($this->creator->first_name . ' ' . $this->creator->last_name)
                : null,
            'created_at' => $this->created_at?->format('d-m-Y H:i:s'),
            'replies_count' => $this->whenLoaded('replies', fn() => $this->replies->count()),
        ];
    }
}
