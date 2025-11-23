<?php

namespace App\Services;

use App\Models\OfficeShift;
use App\Models\UserDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for overtime calculations and validations.
 * Handles time calculations, shift validation, and holiday checks.
 */
class OvertimeCalculationService
{
    /**
     * Calculate total hours between clock in and clock out.
     * Returns time in H:i format.
     */
    public function calculateTotalHours(string $clockIn, string $clockOut): string
    {
        $clockInTime = \DateTime::createFromFormat('Y-m-d H:i:s', $clockIn);
        $clockOutTime = \DateTime::createFromFormat('Y-m-d H:i:s', $clockOut);

        if (!$clockInTime || !$clockOutTime) {
            Log::error('OvertimeCalculationService: Invalid datetime format', [
                'clock_in' => $clockIn,
                'clock_out' => $clockOut
            ]);
            throw new \Exception('تنسيق التاريخ والوقت غير صحيح');
        }

        $interval = $clockInTime->diff($clockOutTime);
        $hours = $interval->h + ($interval->days * 24);
        $minutes = $interval->i;

        return sprintf('%d:%02d', $hours, $minutes);
    }

    /**
     * Calculate overtime types based on additional work hours multiplier.
     * Returns array with straight, time_a_half, and double_overtime values.
     */
    public function calculateOvertimeTypes(string $totalHours, int $additionalWorkHours): array
    {
        $straight = '0';
        $timeAHalf = '0';
        $doubleOvertime = '0';

        // Parse total hours
        list($hours, $minutes) = explode(':', $totalHours);
        $totalSeconds = ($hours * 3600) + ($minutes * 60);

        switch ($additionalWorkHours) {
            case 1: // Straight time
                $straight = $totalHours;
                break;
            
            case 2: // Time and a half (1.5x)
                // Store as original time (multiplier applied in payroll)
                $timeAHalf = $totalHours;
                break;
            
            case 3: // Double time (2x)
                // Store as original time (multiplier applied in payroll)
                $doubleOvertime = $totalHours;
                break;
            
            default:
                // No multiplier specified
                $straight = '0';
                $timeAHalf = '0';
                $doubleOvertime = '0';
                break;
        }

        Log::info('OvertimeCalculationService: Calculated overtime types', [
            'total_hours' => $totalHours,
            'additional_work_hours' => $additionalWorkHours,
            'straight' => $straight,
            'time_a_half' => $timeAHalf,
            'double_overtime' => $doubleOvertime
        ]);

        return [
            'straight' => $straight,
            'time_a_half' => $timeAHalf,
            'double_overtime' => $doubleOvertime,
        ];
    }

    /**
     * Calculate compensation banked hours.
     * Returns hours if compensation type is banked (1), null otherwise.
     */
    public function calculateCompensationBanked(string $totalHours, int $compensationType): ?string
    {
        if ($compensationType == 1) {
            return $totalHours;
        }
        
        return null;
    }

    /**
     * Validate overtime against employee shift.
     * Ensures overtime doesn't overlap with regular shift hours.
     * 
     * @throws \Exception if validation fails
     */
    public function validateAgainstShift(
        int $employeeId, 
        string $requestDate, 
        string $clockIn, 
        string $clockOut, 
        int $overtimeReason
    ): void {
        // Get employee's shift
        $userDetails = UserDetails::where('user_id', $employeeId)->first();
        
        if (!$userDetails || !$userDetails->office_shift_id) {
            Log::warning('OvertimeCalculationService: No shift assigned', [
                'employee_id' => $employeeId
            ]);
            // Allow if no shift assigned
            return;
        }

        $shift = OfficeShift::find($userDetails->office_shift_id);
        
        if (!$shift) {
            Log::warning('OvertimeCalculationService: Shift not found', [
                'shift_id' => $userDetails->office_shift_id
            ]);
            return;
        }

        // Get day of week
        $date = \DateTime::createFromFormat('Y-m-d', $requestDate);
        $day = strtolower($date->format('l'));

        // Get shift times for this day
        $shiftInField = $day . '_in_time';
        $shiftOutField = $day . '_out_time';
        $lunchStartField = $day . '_lunch_break';
        $lunchEndField = $day . '_lunch_break_out';

        $shiftInTime = $shift->$shiftInField;
        $shiftOutTime = $shift->$shiftOutField;
        $lunchStart = $shift->$lunchStartField;
        $lunchEnd = $shift->$lunchEndField;

        // If no shift time for this day, it's a day off - allow overtime
        if (empty($shiftInTime) || empty($shiftOutTime)) {
            return;
        }

        // Parse overtime times (only time portion)
        $overtimeStart = date('H:i', strtotime($clockIn));
        $overtimeEnd = date('H:i', strtotime($clockOut));

        // For work through lunch (reason 2), validate it's within lunch break
        if ($overtimeReason == 2) {
            if (empty($lunchStart) || empty($lunchEnd)) {
                throw new \Exception('لا يوجد وقت استراحة محدد في الوردية');
            }

            // Check if overtime is within lunch break
            if (!($overtimeStart >= $lunchStart && $overtimeEnd <= $lunchEnd)) {
                throw new \Exception('يجب أن يكون العمل أثناء الاستراحة ضمن وقت الاستراحة المحدد');
            }

            return; // Valid lunch break overtime
        }

        // For other overtime reasons, ensure no overlap with shift hours
        if ($overtimeReason != 2) {
            // Check if overtime overlaps with shift time
            $overlapStart = ($overtimeStart >= $shiftInTime && $overtimeStart <= $shiftOutTime);
            $overlapEnd = ($overtimeEnd >= $shiftInTime && $overtimeEnd <= $shiftOutTime);

            if ($overlapStart || $overlapEnd) {
                throw new \Exception('لا يمكن تقديم طلب عمل إضافي خلال ساعات الدوام الرسمي');
            }
        }

        Log::info('OvertimeCalculationService: Shift validation passed', [
            'employee_id' => $employeeId,
            'request_date' => $requestDate,
            'overtime_reason' => $overtimeReason
        ]);
    }

    /**
     * Check if date is a company holiday.
     */
    public function isHoliday(int $companyId, string $date): bool
    {
        $isHoliday = DB::table('ci_holidays')
            ->where('company_id', $companyId)
            ->where('is_publish', 1)
            ->whereRaw("? BETWEEN start_date AND end_date", [$date])
            ->exists();

        Log::info('OvertimeCalculationService: Holiday check', [
            'company_id' => $companyId,
            'date' => $date,
            'is_holiday' => $isHoliday
        ]);

        return $isHoliday;
    }

    /**
     * Convert 12-hour format to 24-hour format with date.
     * Input: '2:30 PM', date: '2025-01-15'
     * Output: '2025-01-15 14:30:00'
     */
    public function convertTo24Hour(string $time12Hour, string $date): string
    {
        $datetime = \DateTime::createFromFormat('g:i A', $time12Hour);
        
        if (!$datetime) {
            Log::error('OvertimeCalculationService: Invalid time format', [
                'time' => $time12Hour
            ]);
            throw new \Exception('صيغة الوقت غير صحيحة');
        }

        $time24Hour = $datetime->format('H:i');
        return $date . ' ' . $time24Hour . ':00';
    }

    /**
     * Calculate request month from date.
     * Returns format: Y-m
     */
    public function calculateRequestMonth(string $date): string
    {
        return date('Y-m', strtotime($date));
    }
}

