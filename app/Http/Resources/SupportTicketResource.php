<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SupportTicketResource",
 *     title="Support Ticket Resource",
 *     description="تذكرة دعم فني",
 *     @OA\Property(property="ticket_id", type="integer", example=1),
 *     @OA\Property(property="ticket_code", type="string", example="ABC123"),
 *     @OA\Property(property="company_id", type="integer", example=10),
 *     @OA\Property(property="subject", type="string", example="مشكلة في تسجيل الدخول"),
 *     @OA\Property(property="description", type="string", example="لا أستطيع تسجيل الدخول"),
 *     @OA\Property(property="category_id", type="integer", example=1),
 *     @OA\Property(property="category", type="string", example="technical"),
 *     @OA\Property(property="category_text", type="string", example="تقني"),
 *     @OA\Property(property="category_text_en", type="string", example="Technical"),
 *     @OA\Property(property="ticket_priority", type="integer", example=2),
 *     @OA\Property(property="priority", type="string", example="high"),
 *     @OA\Property(property="priority_text", type="string", example="مرتفع"),
 *     @OA\Property(property="priority_text_en", type="string", example="High"),
 *     @OA\Property(property="priority_color", type="string", example="orange"),
 *     @OA\Property(property="ticket_status", type="integer", example=1),
 *     @OA\Property(property="status", type="string", example="open"),
 *     @OA\Property(property="status_text", type="string", example="مفتوحة"),
 *     @OA\Property(property="status_text_en", type="string", example="Open"),
 *     @OA\Property(property="is_open", type="boolean", example=true),
 *     @OA\Property(property="ticket_remarks", type="string", nullable=true),
 *     @OA\Property(property="created_by", type="integer", example=24),
 *     @OA\Property(property="created_by_name", type="string", example="أحمد محمد"),
 *     @OA\Property(property="created_by_type", type="string", example="employee"),
 *     @OA\Property(property="created_at", type="string", format="datetime"),
 *     @OA\Property(property="replies_count", type="integer", example=5)
 * )
 */
class SupportTicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ticket_id' => $this->ticket_id,
            'ticket_code' => $this->ticket_code,
            'company_id' => $this->company_id,
            'subject' => $this->subject,
            'description' => $this->description,

            // Category - نرجع الاسم والرقم
            'category_id' => $this->category_id,
            'category' => $this->getCategoryName(),
            'category_text' => $this->category_text,
            'category_text_en' => $this->category_text_en,

            // Priority - نرجع الاسم والرقم
            'ticket_priority' => $this->ticket_priority,
            'priority' => $this->getPriorityName(),
            'priority_text' => $this->priority_text,
            'priority_text_en' => $this->priority_text_en,
            'priority_color' => $this->priority_color,

            // Status - نرجع الاسم والرقم
            'ticket_status' => $this->ticket_status,
            'status' => $this->getStatusName(),
            'status_text' => $this->status_text,
            'status_text_en' => $this->status_text_en,
            'is_open' => $this->isOpen(),

            'ticket_remarks' => $this->ticket_remarks,

            // Creator info
            'created_by' => $this->created_by,
            'created_by_name' => $this->when(
                $this->relationLoaded('createdBy'),
                fn() => $this->createdBy?->full_name ?? 'غير معروف'
            ),
            'created_by_type' => $this->when(
                $this->relationLoaded('createdBy'),
                fn() => $this->createdBy?->user_type ?? null
            ),

            'created_at' => $this->created_at,
            'replies_count' => $this->when(
                $this->relationLoaded('replies'),
                fn() => $this->replies->count(),
                fn() => $this->replies_count ?? 0
            ),

            // Include replies if loaded
            'replies' => $this->when(
                $this->relationLoaded('replies'),
                fn() => TicketReplyResource::collection($this->replies)
            ),
        ];
    }

    /**
     * الحصول على اسم الفئة بالإنجليزية
     */
    private function getCategoryName(): string
    {
        return match ($this->category_id) {
            0 => 'general',
            1 => 'technical',
            2 => 'billing',
            3 => 'subscription',
            4 => 'other',
            default => 'unknown',
        };
    }

    /**
     * الحصول على اسم الأولوية بالإنجليزية
     */
    private function getPriorityName(): string
    {
        return match ($this->ticket_priority) {
            1 => 'urgent',
            2 => 'high',
            3 => 'medium',
            4 => 'low',
            default => 'unknown',
        };
    }

    /**
     * الحصول على اسم الحالة بالإنجليزية
     */
    private function getStatusName(): string
    {
        return match ($this->ticket_status) {
            0 => 'closed',
            1 => 'open',
            default => 'unknown',
        };
    }
}
