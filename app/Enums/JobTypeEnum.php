<?php

declare(strict_types=1);

namespace App\Enums;

enum JobTypeEnum: int
{
    case PART_TIME = 0;
    case PERMANENT = 1;
    case CONTRACT = 2;
    case PROBATION = 3;

    public function trans(): string
    {
        return match ($this) {
            self::PART_TIME => 'دوام جزئي',
            self::PERMANENT => 'دائمة',
            self::CONTRACT => 'عقد',
            self::PROBATION => 'تحت التجربة',
        };
    }

    public static function tryTranslate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return self::tryFrom((string)$value)?->trans() ?? (string)$value;
    }

    public static function toArray(): array
    {
        return array_map(fn($case) => [
            'value' => $case->value,
            'label' => $case->trans(),
        ], self::cases());
    }
}
