<?php

namespace App\Enums;

enum AttendenceStatus: string
{
    case PENDING = 'Pending';
    case APPROVED = 'Approved';
    case NOT_APPROVED = 'Not Approved';

    public function labelAr(): string
    {
        return match ($this) {
            self::PENDING => 'قيد الانتظار',
            self::APPROVED => 'مقبول',
            self::NOT_APPROVED => 'غير مقبول',
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
     * Get Enum case from string value or name
     */
    public static function getValue(string $status): ?self
    {
        foreach (self::cases() as $case) {
            if (
                strtolower($case->name) === strtolower($status) ||
                strtolower($case->value) === strtolower($status)
            ) {
                return $case;
            }
        }
        return null;
    }

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
