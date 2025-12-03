<?php

declare(strict_types=1);

namespace App\Enums;

enum OvertimeReasonEnum: int
{
    case STANDBY_PAY = 1;
    case WORK_THROUGH_LUNCH = 2;
    case OUT_OF_TOWN = 3;
    case SALARIED_EMPLOYEE = 4;
    case ADDITIONAL_WORK_HOURS = 5;

    /**
     * Get human-readable label for API responses (English)
     */
    public function label(): string
    {
        return match($this) {
            self::STANDBY_PAY => 'Standby Pay',
            self::WORK_THROUGH_LUNCH => 'Work Through Lunch',
            self::OUT_OF_TOWN => 'Out of Town',
            self::SALARIED_EMPLOYEE => 'Salaried Employee',
            self::ADDITIONAL_WORK_HOURS => 'Additional Work Hours',
        };
    }

    /**
     * Get human-readable label for API responses (Arabic)
     */
    public function labelAr(): string
    {
        return match($this) {
            self::STANDBY_PAY => 'بدل عمل اضافي (مبلغ)',
            self::WORK_THROUGH_LUNCH => 'العمل وقت الاستراحة',
            self::OUT_OF_TOWN => 'تعيين مهمة عمل خارج المدينة',
            self::SALARIED_EMPLOYEE => 'براتب إضافي',
            self::ADDITIONAL_WORK_HOURS => 'ساعات عمل إضافية',
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
                'name' => $case->name,
                'label' => $case->label(),
                'label_ar' => $case->labelAr(),
            ],
            self::cases()
        );
    }
}

