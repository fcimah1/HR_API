<?php

namespace App\Enums;

enum StringStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case SUBMITTED = 'submitted';

    /**
     * Get human-readable label for API responses (English)
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::SUBMITTED => 'Submitted',
        };
    }

    /**
     * Get human-readable label for API responses (Arabic)
     */
    public function labelAr(): string
    {
        return match ($this) {
            self::PENDING => 'قيد الانتظار',
            self::APPROVED => 'مقبول',
            self::REJECTED => 'مرفوض',
            self::SUBMITTED => 'تم التقديم',
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
     * Convert to numerical value for database storage
     */
    public function toNumerical(): int
    {
        return match ($this) {
            self::PENDING => 0,
            self::APPROVED => 1,
            self::REJECTED => 2,
            self::SUBMITTED => 0,
        };
    }

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
