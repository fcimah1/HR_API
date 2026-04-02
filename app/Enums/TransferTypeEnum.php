<?php

namespace App\Enums;

enum TransferTypeEnum: string
{
    case INTERNAL = 'internal';
    case BRANCH = 'branch';
    case INTERCOMPANY = 'intercompany';



    /**
     * Get human-readable label for API responses (Arabic)
     */
    public function labelAr(): string
    {
        return match ($this) {
            self::INTERNAL => 'نقل داخلي من قسم الى قسم',
            self::BRANCH => 'نقل بين الفروع',
            self::INTERCOMPANY => 'نقل بين الشركات',
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

    /**
     * Get comma-separated string of values for validation rules
     * e.g., "internal,branch,intercompany"
     */
    public static function valuesString(): string
    {
        return implode(',', array_map(fn(self $case) => $case->value, self::cases()));
    }
}
