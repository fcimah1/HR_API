<?php

namespace App\Enums;

enum NumericalStatusEnum: int
{
    case PENDING = 1;
    case APPROVED = 2;
    case REJECTED = 3;

    
    /**
     * Get human-readable label for API responses (Arabic)
     */
    public function labelAr(): string
    {
        return match($this) {
            self::PENDING => 'قيد الانتظار',
            self::APPROVED => 'مقبول',
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
}
