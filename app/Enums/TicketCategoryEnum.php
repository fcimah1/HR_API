<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * أنواع/فئات تذاكر الدعم الفني
 */
enum TicketCategoryEnum: int
{
    case GENERAL = 0;      // عام
    case TECHNICAL = 1;    // تقني
    case BILLING = 2;      // فواتير
    case SUBSCRIPTION = 3; // اشتراك
    case OTHER = 4;        // أخرى

    /**
     * الحصول على النص العربي للفئة
     */
    public function labelAr(): string
    {
        return match ($this) {
            self::GENERAL => 'عام',
            self::TECHNICAL => 'تقني',
            self::BILLING => 'فواتير',
            self::SUBSCRIPTION => 'اشتراك',
            self::OTHER => 'أخرى',
        };
    }

    /**
     * الحصول على النص الإنجليزي للفئة
     */
    public function labelEn(): string
    {
        return match ($this) {
            self::GENERAL => 'General',
            self::TECHNICAL => 'Technical',
            self::BILLING => 'Billing',
            self::SUBSCRIPTION => 'Subscription',
            self::OTHER => 'Other',
        };
    }

    /**
     * الحصول على جميع الفئات كمصفوفة
     */
    public static function toArray(): array
    {
        return array_map(
            fn(self $case) => [
                'value' => $case->value,
                'label_ar' => $case->labelAr(),
                'label_en' => $case->labelEn(),
            ],
            self::cases()
        );
    }

    /**
     * التحقق من صحة القيمة
     */
    public static function isValid(int $value): bool
    {
        return in_array($value, array_column(self::cases(), 'value'), true);
    }

    /**
     * الحصول على الـ Enum من الاسم (يدعم الاسم الإنجليزي أو العربي أو الرقم)
     */
    public static function fromName(string|int $name): ?self
    {
        // إذا كان رقم، نرجعه مباشرة
        if (is_int($name) || is_numeric($name)) {
            return self::tryFrom((int) $name);
        }

        $name = strtolower(trim($name));

        return match ($name) {
            'general', 'عام' => self::GENERAL,
            'technical', 'تقني' => self::TECHNICAL,
            'billing', 'فواتير' => self::BILLING,
            'subscription', 'اشتراك' => self::SUBSCRIPTION,
            'other', 'أخرى' => self::OTHER,
            default => null,
        };
    }

    /**
     * الحصول على جميع الأسماء المقبولة
     */
    public static function getAcceptedNames(): array
    {
        return [
            'general',
            'technical',
            'billing',
            'subscription',
            'other',
            'عام',
            'تقني',
            'فواتير',
            'اشتراك',
            'أخرى',
        ];
    }
}
