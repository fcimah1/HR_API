<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="InternalTicketReply",
 *     @OA\Property(property="ticket_reply_id", type="integer"),
 *     @OA\Property(property="company_id", type="integer"),
 *     @OA\Property(property="ticket_id", type="integer"),
 *     @OA\Property(property="sent_by", type="integer"),
 *     @OA\Property(property="assign_to", type="integer"),
 *     @OA\Property(property="reply_text", type="string")
 * )
 */
class InternalTicketReply extends Model
{
    protected $table = 'ci_support_ticket_reply';
    protected $primaryKey = 'ticket_reply_id';
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'ticket_id',
        'sent_by',
        'assign_to',
        'reply_text',
        'created_at',
    ];

    protected $casts = [
        'ticket_reply_id' => 'integer',
        'company_id' => 'integer',
        'ticket_id' => 'integer',
        'sent_by' => 'integer',
        'assign_to' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * علاقة مع التذكرة
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(InternalSupportTicket::class, 'ticket_id', 'ticket_id');
    }

    /**
     * علاقة مع المرسل
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by', 'user_id');
    }

    /**
     * علاقة مع الموظف المعين
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assign_to', 'user_id');
    }
}
