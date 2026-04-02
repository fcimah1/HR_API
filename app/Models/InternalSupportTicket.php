<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\TicketPriorityEnum;

/**
 * @OA\Schema(
 *     schema="InternalSupportTicket",
 *     @OA\Property(property="ticket_id", type="integer"),
 *     @OA\Property(property="company_id", type="integer"),
 *     @OA\Property(property="ticket_code", type="string"),
 *     @OA\Property(property="subject", type="string"),
 *     @OA\Property(property="employee_id", type="integer"),
 *     @OA\Property(property="department_id", type="integer"),
 *     @OA\Property(property="ticket_priority", type="integer"),
 *     @OA\Property(property="ticket_status", type="integer"),
 *     @OA\Property(property="created_by", type="integer")
 * )
 */
class InternalSupportTicket extends Model
{
    protected $table = 'ci_support_tickets';
    protected $primaryKey = 'ticket_id';
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'ticket_code',
        'subject',
        'employee_id',
        'ticket_priority',
        'department_id',
        'description',
        'ticket_remarks',
        'ticket_status',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'ticket_id' => 'integer',
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'department_id' => 'integer',
        'ticket_priority' => 'integer',
        'ticket_status' => 'integer',
        'created_by' => 'integer',
        'created_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_OPEN = 1;
    public const STATUS_CLOSED = 2;

    /**
     * التحقق إذا كانت التذكرة مفتوحة
     */
    public function isOpen(): bool
    {
        return $this->ticket_status === self::STATUS_OPEN;
    }

    /**
     * التحقق إذا كانت التذكرة مغلقة
     */
    public function isClosed(): bool
    {
        return $this->ticket_status === self::STATUS_CLOSED;
    }

    /**
     * علاقة مع الشركة
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(User::class, 'company_id', 'user_id');
    }

    /**
     * علاقة مع الموظف المعين
     */
    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    /**
     * علاقة مع منشئ التذكرة
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /**
     * علاقة مع القسم
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    /**
     * علاقة مع الردود
     */
    public function replies(): HasMany
    {
        return $this->hasMany(InternalTicketReply::class, 'ticket_id', 'ticket_id');
    }

    /**
     * الحصول على نص الحالة
     */
    public function getStatusTextAttribute(): string
    {
        return match ($this->ticket_status) {
            self::STATUS_OPEN => 'مفتوحة',
            self::STATUS_CLOSED => 'مغلقة',
            default => 'غير محدد',
        };
    }

    /**
     * الحصول على نص الحالة بالإنجليزية
     */
    public function getStatusTextEnAttribute(): string
    {
        return match ($this->ticket_status) {
            self::STATUS_OPEN => 'Open',
            self::STATUS_CLOSED => 'Closed',
            default => 'Unknown',
        };
    }

    /**
     * الحصول على نص الأولوية
     */
    public function getPriorityTextAttribute(): string
    {
        $priority = TicketPriorityEnum::tryFrom($this->ticket_priority);
        return $priority?->labelEn() ?? 'غير محدد';
    }

    /**
     * الحصول على نص الأولوية بالإنجليزية
     */
    public function getPriorityTextEnAttribute(): string
    {
        $priority = TicketPriorityEnum::tryFrom($this->ticket_priority);
        return $priority?->labelEn() ?? 'Unknown';
    }

    /**
     * الحصول على لون الأولوية
     */
    public function getPriorityColorAttribute(): string
    {
        $priority = TicketPriorityEnum::tryFrom($this->ticket_priority);
        return $priority?->color() ?? 'gray';
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
