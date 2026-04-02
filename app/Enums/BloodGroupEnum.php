<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * فصائل الدم
 */
enum BloodGroupEnum: string
{
    case A_POSITIVE = 'A+';
    case A_NEGATIVE = 'A-';
    case B_POSITIVE = 'B+';
    case B_NEGATIVE = 'B-';
    case AB_POSITIVE = 'AB+';
    case AB_NEGATIVE = 'AB-';
    case O_POSITIVE = 'O+';
    case O_NEGATIVE = 'O-';

    /**
     * الحصول على جميع الفصائل كمصفوفة
     */
    public static function toArray(): array
    {
        return array_map(
            fn(self $case) => [
                'value' => $case->value,
                'label' => $case->value,
            ],
            self::cases()
        );
    }
}
