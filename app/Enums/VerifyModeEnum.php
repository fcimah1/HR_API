<?php

namespace App\Enums;

enum VerifyModeEnum: int
{
    case PASSWORD = 0; // Password
    case FINGERPRINT = 1; // Fingerprint
    case CARD = 2; // Card
    case PASSWORD_FINGERPRINT = 3; // Password+Fingerprint
    case CARD_FINGERPRINT = 4; // Card+Fingerprint
    case FACE = 15; // Face

        /**
     * Get human-readable label for API responses (Arabic)
     */
    public function labelAr(): string
    {
        return match($this) {
            self::PASSWORD => 'كلمة مرور',
            self::FINGERPRINT => 'اصبع بصمة',
            self::CARD => 'بطاقة',
            self::PASSWORD_FINGERPRINT => 'كلمة مرور+اصبع بصمة',
            self::CARD_FINGERPRINT => 'بطاقة+اصبع بصمة',
            self::FACE => 'وجه',
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
                'case_name' => $case->name,
                'case_name_ar' => $case->labelAr(),
            ],
            self::cases()
        );
    }
}
