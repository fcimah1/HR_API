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

    public static function getValidationValues(): array
    {
        $values = array_column(self::cases(), 'value');
        $names = array_column(self::cases(), 'name');
        // Add capitalized names (e.g. APPROVED) and title case (e.g. Approved)
        foreach ($names as $name) {
            $values[] = $name;
            $values[] = ucfirst(strtolower($name));
        }
        return $values;
    }

    public static function getValue(string|int $status): ?int
    {
        if (is_numeric($status)) {
            $value = (int) $status;
            // Validate if value exists
            if (in_array($value, array_column(self::cases(), 'value'))) {
                return $value;
            }
        }

        return match (strtoupper($status)) {
            'PENDING' => self::PENDING->value,
            'APPROVED' => self::APPROVED->value,
            'REJECTED' => self::REJECTED->value,
            default => null,
        };
    }
}
