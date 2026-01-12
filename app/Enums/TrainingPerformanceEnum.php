<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Training Performance Enum
 * تقييم أداء التدريب
 */
enum TrainingPerformanceEnum: int
{
    case NOT_FINISHED = 0;  // غير منتهى
    case SATISFACTORY = 1;  // مرضٍ
    case AVERAGE = 2;       // متوسط
    case WEAK = 3;          // ضعيف
    case EXCELLENT = 4;     // ممتاز

    /**
     * Get Arabic label
     */
    public function label(): string
    {
        return match ($this) {
            self::NOT_FINISHED => 'غير منتهى',
            self::SATISFACTORY => 'مرضٍ',
            self::AVERAGE => 'متوسط',
            self::WEAK => 'ضعيف',
            self::EXCELLENT => 'ممتاز',
        };
    }

    /**
     * Get all performance levels as array for dropdowns
     */
    public static function toArray(): array
    {
        return array_map(
            fn(self $performance) => [
                'value' => $performance->value,
                'name' => $performance->name,
                'label' => $performance->label(),
            ],
            self::cases()
        );
    }
}
