<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * الحالة الاجتماعية
 */
enum MaritalStatusEnum: int
{
    case SINGLE = 1;    // أعزب
    case MARRIED = 2;   // متزوج
    case WIDOWED = 3;   // أرمل
    case DIVORCED = 4;  // مطلق

    /**
     * الحصول على النص العربي
     */
    public function labelAr(): string
    {
        return match ($this) {
            self::SINGLE => 'أعزب',
            self::MARRIED => 'متزوج',
            self::WIDOWED => 'أرمل',
            self::DIVORCED => 'مطلق',
        };
    }

    /**
     * الحصول على النص الإنجليزي
     */
    public function labelEn(): string
    {
        return match ($this) {
            self::SINGLE => 'Single',
            self::MARRIED => 'Married',
            self::WIDOWED => 'Widowed',
            self::DIVORCED => 'Divorced',
        };
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
}
