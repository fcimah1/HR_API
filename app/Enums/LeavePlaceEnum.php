<?php

namespace App\Enums;

enum LeavePlaceEnum: string
{
    // 0 => outside 1 => inside
    case OUTSIDE = '0';
    case INSIDE = '1';

    /**
     * Get human-readable label for API responses (English)
     */
    public function label(): string
    {
        return match($this) {
            self::OUTSIDE => '0',
            self::INSIDE => '1',
        };
    }

    /**
     * Get human-readable label for API responses (Arabic)
     */
    public function labelAr(): string
    {
        return match($this) {
            self::OUTSIDE => 'خارجى ',
            self::INSIDE => 'داخلى',
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
                'case_name' => $case->name,
                'case_name_ar' => $case->labelAr(),
            ],
            self::cases()
        );
    }
}
