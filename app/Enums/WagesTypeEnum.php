<?php

declare(strict_types=1);

namespace App\Enums;

enum WagesTypeEnum: int
{
    case MONTHLY = 1;
    case DAILY = 2;
    case HOURLY = 3;

    public function trans(): string
    {
        return match ($this) {
            self::MONTHLY => 'شهري',
            self::DAILY => 'يومي',
            self::HOURLY => 'بالساعة',
        };
    }

    public static function tryTranslate(int $value): string
    {
        return self::tryFrom($value)?->trans() ?? '-';
    }

    public static function toArray(): array
    {
        return array_map(fn($case) => [
            'value' => $case->value,
            'label' => $case->trans(),
        ], self::cases());
    }
}
