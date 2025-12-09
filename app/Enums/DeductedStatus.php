<?php

namespace App\Enums;

enum DeductedStatus: string
{ // 0 => no deducted 1=> deducted
    case NOT_DEDUCTED = '0';
    case DEDUCTED = '1'; 

    /**
     * Get human-readable label for API responses (English)
     */
    public function label(): string
    {
        return match($this) {
            self::NOT_DEDUCTED => 'Not Deducted',
            self::DEDUCTED => 'Deducted',

        };
    }

    /**
     * Get human-readable label for API responses (Arabic)
     */
    public function labelAr(): string
    {
        return match($this) {
            self::NOT_DEDUCTED => 'لا يخصم',
            self::DEDUCTED => 'يخصم',
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
