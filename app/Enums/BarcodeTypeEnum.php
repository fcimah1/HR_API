<?php

declare(strict_types=1);

namespace App\Enums;

enum BarcodeTypeEnum: string
{
    case CODE39 = 'CODE39';
    case CODE93 = 'CODE93';
    case CODE128 = 'CODE128';
    case ISBN = 'ISBN';
    case CODABAR = 'CODABAR';
    case POSTNET = 'POSTNET';
    case EAN8 = 'EAN-8';
    case EAN13 = 'EAN-13';
    case UPCA = 'UPC-A';
    case UPCE = 'UPC-E';

    public function trans(): string
    {
        return $this->value;
    }

    public static function tryTranslate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return self::tryFrom((string)$value)?->trans() ?? (string)$value;
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
