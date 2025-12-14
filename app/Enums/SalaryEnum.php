<?php

declare(strict_types=1);

namespace App\Enums;

enum SalaryEnum: string
{
    case LOAN = 'loan';
    case ADVANCE = 'advance';

    /**
     * Get human-readable label for API responses (English)
     */
    public function label(): string
    {
        return match($this) {
            self::LOAN => 'Loan',
            self::ADVANCE => 'Advance',
        };
    }

    /**
     * Get human-readable label for API responses (Arabic)
     */
    public function labelAr(): string
    {
        return match($this) {
            self::LOAN => 'سلفة',
            self::ADVANCE => 'المرتب مسبقا',
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
                'name' => $case->name,
                'label' => $case->label(),
                'label_ar' => $case->labelAr(),
            ],
            self::cases()
        );
    }
}

