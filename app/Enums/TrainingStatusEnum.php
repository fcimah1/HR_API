<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Training Status Enum
 * حالات التدريب
 */
enum TrainingStatusEnum: int
{
    case PENDING = 0;    // قيد الانتظار
    case STARTED = 1;    // بدأ
    case COMPLETED = 2;  // مكتمل
    case REJECTED = 3;   // مرفوض

    /**
     * Get Arabic label
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'قيد الانتظار',
            self::STARTED => 'بدأ',
            self::COMPLETED => 'مكتمل',
            self::REJECTED => 'مرفوض',
        };
    }

    /**
     * Get all statuses as array for dropdowns
     */
    public static function toArray(): array
    {
        return array_map(
            fn(self $status) => [
                'value' => $status->value,
                'name' => $status->name,
                'label' => $status->label(),
            ],
            self::cases()
        );
    }
}
