<?php

namespace App\Enums;

enum TravelModeEnum: int
{
    case BUS = 1;
    case TRAIN = 2;
    case PLANE = 3;
    case TAXI = 4;
    case RENTAL_CAR = 5;

    /**
     * Get human-readable label for API responses (English)
     */
    public function label(): string
    {
        return match($this) {
            self::BUS => 'Bus',
            self::TRAIN => 'Train',
            self::PLANE => 'Plane',
            self::TAXI => 'Taxi',
            self::RENTAL_CAR => 'Rental Car',
        };
    }

    /**
     * Get human-readable label for API responses (Arabic)
     */
    public function labelAr(): string
    {
        return match($this) {
            self::BUS => 'حافلة',
            self::TRAIN => 'قطار',
            self::PLANE => 'طائرة',
            self::TAXI => 'سيارة أجرة',
            self::RENTAL_CAR => 'سيارة مستأجرة',
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
