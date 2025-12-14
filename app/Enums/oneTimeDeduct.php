<?php

declare(strict_types=1);

namespace App\Enums;

enum oneTimeDeduct: int
{
    case TRUE = 1;
    case FALSE = 0;

    /**
     * Get human-readable label for API responses (English)
     */
    public function label(): string
    {
        return match($this) {
            self::TRUE => 'One Time Deduct',
            self::FALSE => 'Monthly Deduct',
        };
    }

    /**
     * Get human-readable label for API responses (Arabic)
     */
    public function labelAr(): string
    {
        return match($this) {
            self::TRUE => 'خصم لمرة واحدة',
            self::FALSE => 'القسط الشهري',
        };
    }

    /**
     * Get all cases as an array for validation or listing (bilingual)
     */
    public static function toArray(): array
    {
        return array_map(
            fn(self $case) => [
                'value' => $case->value,
                'name' => $case->name,
                'label' => $case->label(),
                'label_ar' => $case->labelAr(),
            ],
            self::cases()
        );
    }
}

