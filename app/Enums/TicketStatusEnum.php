<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالات تذاكر الدعم الفني
 */
enum TicketStatusEnum: int
{
    case OPEN = 1;    // مفتوحة - يمكن تبادل الرسائل
    case CLOSED = 2;  // مغلقة - لا يمكن إضافة ردود

    /**
     * الحصول على النص العربي للحالة
     */
    public function labelAr(): string
    {
        return match ($this) {
            self::OPEN => 'مفتوحة',
            self::CLOSED => 'مغلقة',
        };
    }

    /**
     * الحصول على النص الإنجليزي للحالة
     */
    public function labelEn(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::CLOSED => 'Closed',
        };
    }

    /**
     * التحقق إذا كانت التذكرة مفتوحة
     */
    public function isOpen(): bool
    {
        return $this === self::OPEN;
    }

    /**
     * التحقق إذا كانت التذكرة مغلقة
     */
    public function isClosed(): bool
    {
        return $this === self::CLOSED;
    }

    /**
     * الحصول على جميع الحالات كمصفوفة
     */
    public static function toArray(): array
    {
        return array_map(
            fn(self $case) => [
                'value' => $case->value,
                'label_ar' => $case->labelAr(),
                'label_en' => $case->labelEn(),
            ],
            self::cases()
        );
    }

    /**
     * التحقق من صحة القيمة
     */
    public static function isValid(int $value): bool
    {
        return in_array($value, array_column(self::cases(), 'value'), true);
    }

    /**
     * الحصول على الـ Enum من الاسم (يدعم الاسم الإنجليزي أو العربي أو الرقم)
     */
    public static function fromName(string|int $name): ?self
    {
        // إذا كان رقم، نرجعه مباشرة
        if (is_int($name) || is_numeric($name)) {
            return self::tryFrom((int) $name);
        }

        $name = strtolower(trim($name));

        return match ($name) {
            'open', 'مفتوحة' => self::OPEN,
            'closed', 'مغلقة' => self::CLOSED,
            default => null,
        };
    }

    /**
     * الحصول على جميع الأسماء المقبولة
     */
    public static function getAcceptedNames(): array
    {
        return [
            'open',
            'closed',
            'مفتوحة',
            'مغلقة',
        ];
    }
}
