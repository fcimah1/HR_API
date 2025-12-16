<?php

namespace App\Enums;

enum PunchTypeEnum: int
{
    case CHECK_IN = 0; // Check-In
    case CHECK_OUT = 1; // Check-Out
    case BREAK_OUT = 2; // Break Out
    case BREAK_IN = 3; // Break In
    case OT_IN = 4; // Overtime In
    case OT_OUT = 5; // Overtime Out
    case UNSPECIFIED = 255; // Unspecified


        /**
     * Get human-readable label for API responses (Arabic)
     */
    public function labelAr(): string
    {
        return match($this) {
            self::CHECK_IN => 'حضور',
            self::CHECK_OUT => 'انصراف',
            self::BREAK_OUT => 'نهاية الاستراحة',
            self::BREAK_IN => 'بداية الاستراحة',
            self::OT_IN => 'حضور عمل إضافي',
            self::OT_OUT => 'انصراف عمل إضافي',
            self::UNSPECIFIED => 'غير محدد',
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
