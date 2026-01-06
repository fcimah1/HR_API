<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * أولويات تذاكر الدعم الفني
 */
enum TicketPriorityEnum: int
{
    case URGENT = 1;  // عاجل
    case HIGH = 2;    // مرتفع
    case MEDIUM = 3;  // متوسط
    case LOW = 4;     // منخفض

    /**
     * الحصول على النص العربي للأولوية
     */
    public function labelAr(): string
    {
        return match ($this) {
            self::URGENT => 'عاجل',
            self::HIGH => 'عالي',
            self::MEDIUM => 'متوسط',
            self::LOW => 'قليل',
        };
    }

    /**
     * الحصول على النص الإنجليزي للأولوية
     */
    public function labelEn(): string
    {
        return match ($this) {
            self::URGENT => 'Critical',
            self::HIGH => 'High',
            self::MEDIUM => 'Medium',
            self::LOW => 'Low',
        };
    }

    /**
     * الحصول على اللون المناسب للأولوية (للواجهة)
     */
    public function color(): string
    {
        return match ($this) {
            self::URGENT => 'red',
            self::HIGH => 'orange',
            self::MEDIUM => 'yellow',
            self::LOW => 'green',
        };
    }

    /**
     * الحصول على جميع الأولويات كمصفوفة
     */
    public static function toArray(): array
    {
        return array_map(
            fn(self $case) => [
                'value' => $case->value,
                'label_ar' => $case->labelAr(),
                'label_en' => $case->labelEn(),
                'color' => $case->color(),
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
            'urgent', 'عاجل' => self::URGENT,
            'high', 'مرتفع' => self::HIGH,
            'medium', 'متوسط' => self::MEDIUM,
            'low', 'منخفض' => self::LOW,
            default => null,
        };
    }

    /**
     * الحصول على جميع الأسماء المقبولة
     */
    public static function getAcceptedNames(): array
    {
        return [
            'urgent',
            'high',
            'medium',
            'low',
            'عاجل',
            'مرتفع',
            'متوسط',
            'منخفض',
        ];
    }
}
