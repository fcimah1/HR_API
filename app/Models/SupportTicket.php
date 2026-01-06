<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TicketCategoryEnum;
use App\Enums\TicketPriorityEnum;
use App\Enums\TicketStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'ci_company_tickets';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'ticket_id';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * Status constants
     */
    public const STATUS_CLOSED = 2;
    public const STATUS_OPEN = 1;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'ticket_code',
        'subject',
        'ticket_priority',
        'category_id',
        'description',
        'ticket_remarks',
        'ticket_status',
        'created_by',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'company_id' => 'integer',
        'ticket_priority' => 'integer',
        'category_id' => 'integer',
        'ticket_status' => 'integer',
        'created_by' => 'integer',
    ];

    /**
     * Get the company that owns the ticket.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(User::class, 'company_id', 'user_id');
    }

    /**
     * Get the user who created the ticket.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /**
     * Get the replies for this ticket.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class, 'ticket_id', 'ticket_id')
            ->orderBy('created_at', 'asc');
    }

    /**
     * Get the status enum.
     */
    public function getStatusEnumAttribute(): ?TicketStatusEnum
    {
        return TicketStatusEnum::tryFrom($this->ticket_status);
    }

    /**
     * Get the category enum.
     */
    public function getCategoryEnumAttribute(): ?TicketCategoryEnum
    {
        return TicketCategoryEnum::tryFrom($this->category_id);
    }

    /**
     * Get the priority enum.
     */
    public function getPriorityEnumAttribute(): ?TicketPriorityEnum
    {
        return TicketPriorityEnum::tryFrom($this->ticket_priority);
    }

    /**
     * Get status text in Arabic.
     */
    public function getStatusTextAttribute(): string
    {
        return $this->statusEnum?->labelAr() ?? 'غير محدد';
    }

    /**
     * Get status text in English.
     */
    public function getStatusTextEnAttribute(): string
    {
        return $this->statusEnum?->labelEn() ?? 'Unknown';
    }

    /**
     * Get category text in Arabic.
     */
    public function getCategoryTextAttribute(): string
    {
        return $this->categoryEnum?->labelAr() ?? 'غير محدد';
    }

    /**
     * Get category text in English.
     */
    public function getCategoryTextEnAttribute(): string
    {
        return $this->categoryEnum?->labelEn() ?? 'Unknown';
    }

    /**
     * Get priority text in Arabic.
     */
    public function getPriorityTextAttribute(): string
    {
        return $this->priorityEnum?->labelAr() ?? 'غير محدد';
    }

    /**
     * Get priority text in English.
     */
    public function getPriorityTextEnAttribute(): string
    {
        return $this->priorityEnum?->labelEn() ?? 'Unknown';
    }

    /**
     * Get priority color.
     */
    public function getPriorityColorAttribute(): string
    {
        return $this->priorityEnum?->color() ?? 'gray';
    }

    /**
     * Check if ticket is open.
     */
    public function isOpen(): bool
    {
        return $this->ticket_status === self::STATUS_OPEN;
    }

    /**
     * Check if ticket is closed.
     */
    public function isClosed(): bool
    {
        return $this->ticket_status === self::STATUS_CLOSED;
    }

    /**
     * Get the latest reply for this ticket.
     */
    public function latestReply(): HasMany
    {
        return $this->hasMany(TicketReply::class, 'ticket_id', 'ticket_id')
            ->orderBy('created_at', 'desc')
            ->limit(1);
    }

    /**
     * Get the replies count.
     */
    public function getRepliesCountAttribute(): int
    {
        return $this->replies()->count();
    }

    /**
     * Generate a unique ticket code.
     */
    public static function generateTicketCode(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        // تأكد من عدم وجود كود مكرر
        while (self::where('ticket_code', $code)->exists()) {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        }

        return $code;
    }
}
