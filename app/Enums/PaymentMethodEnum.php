<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethodEnum: string
{
    case CASH = 'CASH';
    case DEPOSIT = 'DEPOSIT';
    case BANK = 'BANK';

    public function trans(): string
    {
        return match ($this) {
            self::CASH => 'نقد',
            self::DEPOSIT, self::BANK => 'إيداع',
        };
    }

    public static function tryTranslate(?string $value): string
    {
        if (!$value) {
            return '-';
        }

        return self::tryFrom(strtoupper($value))?->trans() ?? $value;
    }

    public static function toArray(): array
    {
        return array_map(fn($case) => [
            'value' => $case->value,
            'label' => $case->trans(),
        ], self::cases());
    }
}
