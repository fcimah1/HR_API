<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceStatusEnum: string
{
    case PRESENT = 'Present';
    case ABSENT = 'Absent';
    case LATE = 'Late';
    case HALF_DAY = 'Half Day';
    case ON_LEAVE = 'On Leave';

    public function trans(): string
    {
        return match ($this) {
            self::PRESENT => 'حاضر',
            self::ABSENT => 'غائب',
            self::LATE => 'متأخر',
            self::HALF_DAY => 'نصف يوم',
            self::ON_LEAVE => 'إجازة',
        };
    }

    public static function tryTranslate(?string $value): string
    {
        if (!$value) {
            return '-';
        }

        return self::tryFrom($value)?->trans() ?? $value;
    }

    public static function toArray(): array
    {
        return array_map(fn($case) => [
            'value' => $case->value,
            'label' => $case->trans(),
        ], self::cases());
    }
}
