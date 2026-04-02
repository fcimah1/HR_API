<?php

namespace App\Enums;

enum RelativePlace: int
{
    case OUTSIDE_COUNTRY = 0;
    case INSIDE_COUNTRY = 1;

    /**
     * Get Arabic label for the place
     */
    public function getArabicLabel(): string
    {
        return match($this) {
            self::OUTSIDE_COUNTRY => 'خارج البلد',
            self::INSIDE_COUNTRY => 'داخل البلد',
        };
    }

    /**
     * Get English label for the place
     */
    public function getEnglishLabel(): string
    {
        return match($this) {
            self::OUTSIDE_COUNTRY => 'Outside Country',
            self::INSIDE_COUNTRY => 'Inside Country',
        };
    }

    /**
     * Get all places as array with Arabic labels
     */
    public static function getArabicOptions(): array
    {
        return [
            self::OUTSIDE_COUNTRY->value => self::OUTSIDE_COUNTRY->getArabicLabel(),
            self::INSIDE_COUNTRY->value => self::INSIDE_COUNTRY->getArabicLabel(),
        ];
    }

    /**
     * Get all places as array with English labels
     */
    public static function getEnglishOptions(): array
    {
        return [
            self::OUTSIDE_COUNTRY->value => self::OUTSIDE_COUNTRY->getEnglishLabel(),
            self::INSIDE_COUNTRY->value => self::INSIDE_COUNTRY->getEnglishLabel(),
        ];
    }

    /**
     * Create from Arabic label
     */
    public static function fromArabicLabel(string $label): ?self
    {
        return match($label) {
            'خارج البلد' => self::OUTSIDE_COUNTRY,
            'داخل البلد' => self::INSIDE_COUNTRY,
            default => null,
        };
    }

    /**
     * Create from English label
     */
    public static function fromEnglishLabel(string $label): ?self
    {
        return match($label) {
            'Outside Country' => self::OUTSIDE_COUNTRY,
            'Inside Country' => self::INSIDE_COUNTRY,
            default => null,
        };
    }

    /**
     * Validate if a value is a valid place
     */
    public static function isValid(int $value): bool
    {
        return in_array($value, [0, 1]);
    }

    /**
     * Get all valid values
     */
    public static function getValues(): array
    {
        return [0, 1];
    }

    public static function toArray(): array
    {
        return [
            ['value' => self::OUTSIDE_COUNTRY->value, 'label_ar' => self::OUTSIDE_COUNTRY->getArabicLabel(), 'label_en' => self::OUTSIDE_COUNTRY->getEnglishLabel()],
            ['value' => self::INSIDE_COUNTRY->value, 'label_ar' => self::INSIDE_COUNTRY->getArabicLabel(), 'label_en' => self::INSIDE_COUNTRY->getEnglishLabel()],
        ];
    }
}
