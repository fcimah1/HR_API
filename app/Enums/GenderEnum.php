<?php

namespace App\Enums;

enum GenderEnum: int
{
    case MALE = 0;
    case FEMALE = 1;
    case NO_PREFERENCE = 2;

    public function label(): string
    {
        return match ($this) {
            self::MALE => 'ذكر',
            self::FEMALE => 'أنثى',
            self::NO_PREFERENCE => 'غير محدد',
        };
    }

    public static function toArray(): array
    {
        return array_map(fn($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}
