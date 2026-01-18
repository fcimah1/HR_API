<?php

namespace App\Enums;

enum NumericalStatusEnum: int
{
    case PENDING = 0;
    case APPROVED = 1;
    case REJECTED = 2;



    /**
     * Get human-readable label for API responses (Arabic)
     */
    public function labelAr(): string
    {
        return match ($this) {
            self::PENDING => 'قيد الانتظار',
            self::APPROVED => 'تم الموافقة عليه',
            self::REJECTED => 'مرفوض',
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
     * Get all values as a simple comma-separated string for validation rules
     */
    public static function valuesString(): string
    {
        return implode(',', array_column(self::cases(), 'value'));
    }
}
