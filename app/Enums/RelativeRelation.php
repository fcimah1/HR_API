<?php

namespace App\Enums;

enum RelativeRelation: int
{
    case FIRST_DEGREE = 1;
    case SECOND_DEGREE = 2;
    case THIRD_DEGREE = 3;
    case FOURTH_DEGREE = 4;

    /**
     * Get Arabic label for the relation
     */
    public function getArabicLabel(): string
    {
        return match($this) {
            self::FIRST_DEGREE => 'الدرجة الأولى',
            self::SECOND_DEGREE => 'الدرجة الثانية',
            self::THIRD_DEGREE => 'الدرجة الثالثة',
            self::FOURTH_DEGREE => 'الدرجة الرابعة',
        };
    }

    /**
     * Get English label for the relation
     */
    public function getEnglishLabel(): string
    {
        return match($this) {
            self::FIRST_DEGREE => 'First Degree',
            self::SECOND_DEGREE => 'Second Degree',
            self::THIRD_DEGREE => 'Third Degree',
            self::FOURTH_DEGREE => 'Fourth Degree',
        };
    }

    /**
     * Get all relations as array with Arabic labels
     */
    public static function getArabicOptions(): array
    {
        return [
            self::FIRST_DEGREE->value => self::FIRST_DEGREE->getArabicLabel(),
            self::SECOND_DEGREE->value => self::SECOND_DEGREE->getArabicLabel(),
            self::THIRD_DEGREE->value => self::THIRD_DEGREE->getArabicLabel(),
            self::FOURTH_DEGREE->value => self::FOURTH_DEGREE->getArabicLabel(),
        ];
    }

    /**
     * Get all relations as array with English labels
     */
    public static function getEnglishOptions(): array
    {
        return [
            self::FIRST_DEGREE->value => self::FIRST_DEGREE->getEnglishLabel(),
            self::SECOND_DEGREE->value => self::SECOND_DEGREE->getEnglishLabel(),
            self::THIRD_DEGREE->value => self::THIRD_DEGREE->getEnglishLabel(),
            self::FOURTH_DEGREE->value => self::FOURTH_DEGREE->getEnglishLabel(),
        ];
    }

    /**
     * Create from Arabic label
     */
    public static function fromArabicLabel(string $label): ?self
    {
        return match($label) {
            'الدرجة الأولى' => self::FIRST_DEGREE,
            'الدرجة الثانية' => self::SECOND_DEGREE,
            'الدرجة الثالثة' => self::THIRD_DEGREE,
            'الدرجة الرابعة' => self::FOURTH_DEGREE,
            default => null,
        };
    }

    /**
     * Create from English label
     */
    public static function fromEnglishLabel(string $label): ?self
    {
        return match($label) {
            'First Degree' => self::FIRST_DEGREE,
            'Second Degree' => self::SECOND_DEGREE,
            'Third Degree' => self::THIRD_DEGREE,
            'Fourth Degree' => self::FOURTH_DEGREE,
            default => null,
        };
    }

    /**
     * Validate if a value is a valid relation
     */
    public static function isValid(int $value): bool
    {
        return in_array($value, [1, 2, 3, 4]);
    }

    /**
     * Get all valid values
     */
    public static function getValues(): array
    {
        return [1, 2, 3, 4];
    }

    public static function toArray(): array
    {
        return [
            ['value' => self::FIRST_DEGREE->value, 'label_ar' => self::FIRST_DEGREE->getArabicLabel(), 'label_en' => self::FIRST_DEGREE->getEnglishLabel()],
            ['value' => self::SECOND_DEGREE->value, 'label_ar' => self::SECOND_DEGREE->getArabicLabel(), 'label_en' => self::SECOND_DEGREE->getEnglishLabel()],
            ['value' => self::THIRD_DEGREE->value, 'label_ar' => self::THIRD_DEGREE->getArabicLabel(), 'label_en' => self::THIRD_DEGREE->getEnglishLabel()],
            ['value' => self::FOURTH_DEGREE->value, 'label_ar' => self::FOURTH_DEGREE->getArabicLabel(), 'label_en' => self::FOURTH_DEGREE->getEnglishLabel()],
        ];
    }
}
