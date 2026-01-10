<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketReply extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'ci_company_tickets_reply';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'ticket_reply_id';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'ticket_id',
        'sent_by',
        'assign_to',
        'reply_text',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'company_id' => 'integer',
        'ticket_id' => 'integer',
        'sent_by' => 'integer',
        'assign_to' => 'integer',
    ];

    /**
     * Get the ticket that owns this reply.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id', 'ticket_id');
    }

    /**
     * Get the user who sent this reply.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by', 'user_id');
    }

    /**
     * Get the user this reply is assigned to.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assign_to', 'user_id');
    }

    /**
     * Get sender name.
     */
    public function getSenderNameAttribute(): string
    {
        return $this->sender?->full_name ?? 'غير معروف';
    }

    /**
     * Get assignee name.
     */
    public function getAssigneeNameAttribute(): string
    {
        return $this->assignee?->full_name ?? 'غير معروف';
    }

    /**
     * Check if sender is super user.
     */
    public function isSenderSuperUser(): bool
    {
        return $this->sender?->user_type === 'super_user';
    }

    /**
     * Format created_at for display.
     */
    public function getFormattedCreatedAtAttribute(): string
    {
        if (empty($this->created_at)) {
            return '';
        }

        $date = $this->created_at;
        if (is_string($date)) {
            $date = \Carbon\Carbon::parse($date);
        }

        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Get time ago format.
     */
    public function getTimeAgoAttribute(): string
    {
        if (empty($this->created_at)) {
            return '';
        }

        $date = $this->created_at;
        if (is_string($date)) {
            $date = \Carbon\Carbon::parse($date);
        }

        return $date->diffForHumans();
    }
}
