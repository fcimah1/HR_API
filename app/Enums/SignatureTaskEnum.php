<?php

declare(strict_types=1);

namespace App\Enums;

enum SignatureTaskEnum: int
{
    case ONBOARDING = 1;
    case OFFBOARDING = 0;

    public function trans(): string
    {
        return match ($this) {
            self::ONBOARDING => 'ملف onboarding',
            self::OFFBOARDING => 'ملف offboarding',
        };
    }

    public static function tryTranslate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return self::tryFrom((int)$value)?->trans() ?? (string)$value;
    }

    public static function toArray(): array
    {
        return array_map(fn($case) => [
            'value' => $case->value,
            'label' => $case->trans(),
        ], self::cases());
    }
}
