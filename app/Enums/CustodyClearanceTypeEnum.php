<?php

namespace App\Enums;

enum CustodyClearanceTypeEnum: string
{
    case RESIGNATION = 'resignation';
    case END_OF_CONTRACT = 'end_of_contract';
    case TRANSFER = 'transfer';
    case TERMINATION = 'termination';
    case OTHER = 'other';

    /**
     * Get human-readable label for API responses (Arabic)
     */
    public function labelAr(): string
    {
        return match ($this) {
            self::RESIGNATION => 'استقالة',
            self::END_OF_CONTRACT => 'انتهاء العقد',
            self::TRANSFER => 'نقل',
            self::TERMINATION => 'إنهاء خدمة',
            self::OTHER => 'أخرى',
        };
    }

    /**
     * Get human-readable label for API responses (English)
     */
    public function labelEn(): string
    {
        return match ($this) {
            self::RESIGNATION => 'Resignation',
            self::END_OF_CONTRACT => 'End of Contract',
            self::TRANSFER => 'Transfer',
            self::TERMINATION => 'Termination',
            self::OTHER => 'Other',
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
                'label_ar' => $case->labelAr(),
            ],
            self::cases()
        );
    }

    /**
     * Get valid values for validation
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
