<?php

namespace App\Enums\Recruitment;

enum CandidateStatusEnum: int
{
    case PENDING = 0;
    case INVITED_TO_INTERVIEW = 1;
    case REJECTED = 2;

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'قيد الانتظار',
            self::INVITED_TO_INTERVIEW => 'دعوة للمقابلة',
            self::REJECTED => 'مرفوض',
        };
    }

    public static function rejected(): self
    {
        return self::REJECTED;
    }

    public static function pending(): self
    {
        return self::PENDING;
    }

    public static function invited_to_interview(): self
    {
        return self::INVITED_TO_INTERVIEW;
    }
}
