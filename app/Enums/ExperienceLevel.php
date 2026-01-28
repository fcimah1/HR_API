<?php

namespace App\Enums;

enum ExperienceLevel: int
{
    case NONE = 0;
    case ONE_YEAR = 1;
    case TWO_YEARS = 2;
    case THREE_YEARS = 3;
    case FOUR_YEARS = 4;
    case FIVE_YEARS = 5;
    case SIX_YEARS = 6;
    case SEVEN_YEARS = 7;
    case EIGHT_YEARS = 8;
    case NINE_YEARS = 9;
    case TEN_YEARS = 10;
    case MORE_THAN_TEN = 11;

    /**
     * Get the Arabic label for the experience level
     */
    public function getArabicLabel(): string
    {
        return match($this) {
            self::NONE => 'بدون',
            self::ONE_YEAR => 'سنة',
            self::TWO_YEARS => 'سنتان',
            self::THREE_YEARS => 'سنوات 3',
            self::FOUR_YEARS => 'سنوات 4',
            self::FIVE_YEARS => 'سنوات 5',
            self::SIX_YEARS => 'سنوات 6',
            self::SEVEN_YEARS => 'سنوات 7',
            self::EIGHT_YEARS => 'سنوات 8',
            self::NINE_YEARS => 'سنوات 9',
            self::TEN_YEARS => 'سنوات 10',
            self::MORE_THAN_TEN => 'أكثر من 10+',
        };
    }

    /**
     * Get the English label for the experience level
     */
    public function getEnglishLabel(): string
    {
        return match($this) {
            self::NONE => 'No Experience',
            self::ONE_YEAR => '1 Year',
            self::TWO_YEARS => '2 Years',
            self::THREE_YEARS => '3 Years',
            self::FOUR_YEARS => '4 Years',
            self::FIVE_YEARS => '5 Years',
            self::SIX_YEARS => '6 Years',
            self::SEVEN_YEARS => '7 Years',
            self::EIGHT_YEARS => '8 Years',
            self::NINE_YEARS => '9 Years',
            self::TEN_YEARS => '10 Years',
            self::MORE_THAN_TEN => '10+ Years',
        };
    }

    /**
     * Get all experience levels as an array for dropdowns
     */
    public static function toArray(): array
    {
        $levels = [];
        foreach (self::cases() as $level) {
            $levels[] = [
                'value' => $level->value,
                'label_ar' => $level->getArabicLabel(),
                'label_en' => $level->getEnglishLabel(),
            ];
        }
        return $levels;
    }

    /**
     * Get experience level from value
     */
    public static function fromValue(int $value): ?self
    {
        foreach (self::cases() as $level) {
            if ($level->value === $value) {
                return $level;
            }
        }
        return null;
    }

    /**
     * Check if the experience level is entry level (0-2 years)
     */
    public function isEntryLevel(): bool
    {
        return in_array($this, [self::NONE, self::ONE_YEAR, self::TWO_YEARS]);
    }

    /**
     * Check if the experience level is mid-level (3-6 years)
     */
    public function isMidLevel(): bool
    {
        return in_array($this, [self::THREE_YEARS, self::FOUR_YEARS, self::FIVE_YEARS, self::SIX_YEARS]);
    }

    /**
     * Check if the experience level is senior level (7+ years)
     */
    public function isSeniorLevel(): bool
    {
        return in_array($this, [
            self::SEVEN_YEARS, 
            self::EIGHT_YEARS, 
            self::NINE_YEARS, 
            self::TEN_YEARS, 
            self::MORE_THAN_TEN
        ]);
    }

    /**
     * Get experience range description
     */
    public function getRangeDescription(): string
    {
        if ($this->isEntryLevel()) {
            return 'Entry Level (0-2 years)';
        } elseif ($this->isMidLevel()) {
            return 'Mid Level (3-6 years)';
        } else {
            return 'Senior Level (7+ years)';
        }
    }
}