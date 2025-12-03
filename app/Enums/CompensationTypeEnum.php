<?php

declare(strict_types=1);

namespace App\Enums;

enum CompensationTypeEnum: int
{
    case BANKED = 1;
    case PAYOUT = 2;

    /**
     * Get human-readable label for API responses (English)
     */
    public function label(): string
    {
        return match($this) {
            self::BANKED => 'Banked',
            self::PAYOUT => 'Payout',
        };
    }

    /**
     * Get human-readable label for API responses (Arabic)
     */
    public function labelAr(): string
    {
        return match($this) {
            self::BANKED => 'بنكي',
            self::PAYOUT => 'على الراتب',
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

