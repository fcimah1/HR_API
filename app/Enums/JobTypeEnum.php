<?php

declare(strict_types=1);

namespace App\Enums;

enum JobTypeEnum: int
{
    case PART_TIME = 1;
    case PERMANENT = 2;
    case CONTRACT = 3;
    case PROBATION = 4;

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

        if (is_string($value)) {
            if (is_numeric($value)) {
                $value = (int) $value;
            } else {
                return $value;
            }
        }

        if (!is_int($value)) {
            return (string) $value;
        }

        return self::tryFrom($value)?->trans() ?? (string) $value;
    }

    public static function toArray(): array
    {
        return array_map(fn($case) => [
            'value' => $case->value,
            'label' => $case->trans(),
        ], self::cases());
    }
}
