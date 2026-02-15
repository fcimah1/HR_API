<?php

namespace App\Enums\Recruitment;

enum InterviewStatusEnum: int
{
    case NOT_STARTED = 0;
    case SUCCESSFUL = 1;
    case REJECTED = 2;

    public function label(): string
    {
        return match ($this) {
            self::NOT_STARTED => 'لم يبدأ',
            self::SUCCESSFUL => 'مقابلة ناجحة',
            self::REJECTED => 'مرفوض',
        };
    }

    public static function fromValue(string $value): self
    {
        return match ($value) {
            self::NOT_STARTED->value => self::NOT_STARTED,
            self::SUCCESSFUL->value => self::SUCCESSFUL,
            self::REJECTED->value => self::REJECTED,
            default => null,
        };
    }

    public static function not_started(): self
    {
        return self::NOT_STARTED;
    }

    public static function successful(): self
    {
        return self::SUCCESSFUL;
    }

    public static function rejected(): self
    {
        return self::REJECTED;
    }

    public static function toArray(): array
    {
        return [
            self::NOT_STARTED->value => self::NOT_STARTED->label(),
            self::SUCCESSFUL->value => self::SUCCESSFUL->label(),
            self::REJECTED->value => self::REJECTED->label(),
        ];
    }
}
