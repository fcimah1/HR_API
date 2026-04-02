<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductRatingEnum: int
{
    case UNRATED = 0;
    case VERY_POOR = 1;
    case POOR = 2;
    case AVERAGE = 3;
    case GOOD = 4;
    case EXCELLENT = 5;

    public function trans(): string
    {
        return match ($this) {
            self::UNRATED => 'غير مقيم',
            self::VERY_POOR => 'سيء جداً',
            self::POOR => 'سيء',
            self::AVERAGE => 'متوسط',
            self::GOOD => 'جيد',
            self::EXCELLENT => 'ممتاز',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function toArray(): array
    {
        return array_map(fn($case) => [
            'value' => $case->value,
            'label' => $case->trans(),
        ], self::cases());
    }
}
