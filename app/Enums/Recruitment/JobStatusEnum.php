<?php

namespace App\Enums\Recruitment;

enum JobStatusEnum: int
{
    case UNPUBLISHED = 0;
    case PUBLISHED = 1;

    public function label(): string
    {
        return match ($this) {
            self::UNPUBLISHED => 'غير منشورة',
            self::PUBLISHED => 'تم النشر',
        };
    }
}
