<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\AttendanceStatusEnum;
use App\Enums\JobTypeEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\WagesTypeEnum;

/**
 * Trait يحتوي على دوال مساعدة للتقارير
 * Report Helper Methods Trait
 */
trait ReportHelperTrait
{
    /**
     * Get location text (Inside/Outside branch) based on coordinates
     * تحديد موقع الموظف (داخل/خارج الفرع) بناءً على الإحداثيات
     */
    private function getLocationText($recordLat, $recordLong, $branchCoordinates): string
    {
        if (empty($recordLat) || empty($recordLong)) {
            return '';
        }

        if (empty($branchCoordinates)) {
            return 'خارج الفرع';
        }

        $parts = explode(',', $branchCoordinates);
        if (count($parts) !== 2) {
            return 'خارج الفرع';
        }

        $branchLat = (float)$parts[0];
        $branchLong = (float)$parts[1];

        if (empty($branchLat) || empty($branchLong)) {
            return 'خارج الفرع';
        }

        $dist = $this->calculateDistance((float)$recordLat, (float)$recordLong, $branchLat, $branchLong);
        return ($dist <= 200) ? 'داخل الفرع' : 'خارج الفرع';
    }

    /**
     * Calculate distance between two coordinates in meters
     * حساب المسافة بين إحداثيتين بالمتر
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        if (($lat1 == $lat2) && ($lon1 == $lon2)) {
            return 0;
        }
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        // convert to meters
        return ($miles * 1.609344) * 1000;
    }

    /**
     * Parse duration string (HH:MM or HH:MM:SS) to seconds
     * تحويل المدة الزمنية إلى ثواني
     */
    private function parseDurationToSeconds($duration): int
    {
        if (empty($duration) || $duration === '00:00' || $duration === '00:00:00') {
            return 0;
        }
        // Avoid dates
        if (str_contains((string)$duration, '-')) {
            return 0;
        }

        $parts = explode(':', (string)$duration);
        if (count($parts) >= 2) {
            return (int)$parts[0] * 3600 + (int)$parts[1] * 60;
        }
        return 0;
    }

    /**
     * Format seconds to time string (HH:MM)
     * تحويل الثواني إلى صيغة وقت
     */
    private function formatSecondsToTime(int $seconds): string
    {
        if ($seconds === 0) {
            return '00:00';
        }
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * ترجمة حالة الحضور
     * Translate attendance status to Arabic
     */
    private function translateAttendanceStatus(?string $status): string
    {
        return AttendanceStatusEnum::tryTranslate($status);
    }

    /**
     * ترجمة نوع الوظيفة
     * Translate job type to Arabic
     */
    private function translateJobType(mixed $jobType): string
    {
        return JobTypeEnum::tryTranslate($jobType);
    }

    /**
     * ترجمة طريقة الدفع
     * Translate payment method to Arabic
     */
    private function translatePaymentMethod(?string $method): string
    {
        return PaymentMethodEnum::tryTranslate($method);
    }

    /**
     * نص نوع الراتب
     * Get wages type text in Arabic
     */
    private function getWagesTypeText(int $wagesType): string
    {
        return WagesTypeEnum::tryTranslate($wagesType);
    }

    /**
     * ترجمة أسماء الأيام للعربية
     */
    private function translateDayName(string $dayName): string
    {
        $days = [
            'Saturday'  => 'السبت',
            'Sunday'    => 'الأحد',
            'Monday'    => 'الاثنين',
            'Tuesday'   => 'الثلاثاء',
            'Wednesday' => 'الأربعاء',
            'Thursday'  => 'الخميس',
            'Friday'    => 'الجمعة',
        ];

        return $days[$dayName] ?? $dayName;
    }
}
