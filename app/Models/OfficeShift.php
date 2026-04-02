<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficeShift extends Model
{
    use HasFactory;
    public $timestamps = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'ci_office_shifts';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'office_shift_id';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'shift_name',
        'monday_in_time',
        'monday_out_time',
        'tuesday_in_time',
        'tuesday_out_time',
        'wednesday_in_time',
        'wednesday_out_time',
        'thursday_in_time',
        'thursday_out_time',
        'friday_in_time',
        'friday_out_time',
        'saturday_in_time',
        'saturday_out_time',
        'sunday_in_time',
        'sunday_out_time',
        'monday_lunch_break',
        'tuesday_lunch_break',
        'wednesday_lunch_break',
        'thursday_lunch_break',
        'friday_lunch_break',
        'saturday_lunch_break',
        'sunday_lunch_break',
        'monday_lunch_break_out',
        'tuesday_lunch_break_out',
        'wednesday_lunch_break_out',
        'thursday_lunch_break_out',
        'friday_lunch_break_out',
        'saturday_lunch_break_out',
        'sunday_lunch_break_out',
        'hours_per_day',
        'in_time_beginning',
        'in_time_end',
        'late_allowance',
        'out_time_beginning',
        'out_time_end',
        'break_start',
        'break_end',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'office_shift_id' => 'integer',
        'company_id' => 'integer',
        'hours_per_day' => 'integer',
        'late_allowance' => 'integer',
        'monday_in_time' => 'string',
        'monday_out_time' => 'string',
        'tuesday_in_time' => 'string',
        'tuesday_out_time' => 'string',
        'wednesday_in_time' => 'string',
        'wednesday_out_time' => 'string',
        'thursday_in_time' => 'string',
        'thursday_out_time' => 'string',
        'friday_in_time' => 'string',
        'friday_out_time' => 'string',
        'saturday_in_time' => 'string',
        'saturday_out_time' => 'string',
        'sunday_in_time' => 'string',
        'sunday_out_time' => 'string',
    ];

    /**
     * Get the user details that have this office shift.
     */
    public function userDetails(): HasMany
    {
        return $this->hasMany(UserDetails::class, 'office_shift_id', 'office_shift_id');
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Get the name attribute (alias for shift_name).
     */
    public function getNameAttribute()
    {
        return $this->shift_name;
    }

    /**
     * Get shift times for a specific day
     * @param string $dayName (monday, tuesday, etc.)
     * @return array ['in_time' => string|null, 'out_time' => string|null, 'lunch_start' => string|null, 'lunch_end' => string|null]
     */
    public function getShiftTimesForDay(string $dayName): array
    {
        $day = strtolower($dayName);
        $inTimeField = $day . '_in_time';
        $outTimeField = $day . '_out_time';
        $lunchBreakField = $day . '_lunch_break';
        $lunchBreakOutField = $day . '_lunch_break_out';

        return [
            'in_time' => $this->{$inTimeField} ?? null,
            'out_time' => $this->{$outTimeField} ?? null,
            'lunch_start' => $this->{$lunchBreakField} ?? null,
            'lunch_end' => $this->{$lunchBreakOutField} ?? null,
        ];
    }

    /**
     * Get shift in time for a specific date
     */
    public function getInTimeForDate(string $date): ?string
    {
        $dayName = date('l', strtotime($date));
        $times = $this->getShiftTimesForDay($dayName);
        return $times['in_time'];
    }

    /**
     * Get shift out time for a specific date
     */
    public function getOutTimeForDate(string $date): ?string
    {
        $dayName = date('l', strtotime($date));
        $times = $this->getShiftTimesForDay($dayName);
        return $times['out_time'];
    }

    /**
     * Check if it's a day off (no shift times)
     */
    public function isDayOff(string $date): bool
    {
        $inTime = $this->getInTimeForDate($date);
        return empty($inTime);
    }

    /**
     * Calculate time late (التأخير)
     * @param string $date Date (Y-m-d)
     * @param string $clockInTime Clock in time (Y-m-d H:i:s)
     * @return string Time late in format HH:MM or 00:00 if on time
     */
    public function calculateTimeLate(string $date, string $clockInTime): string
    {
        $shiftInTime = $this->getInTimeForDate($date);

        if (empty($shiftInTime)) {
            return '00:00'; // يوم عطلة - لا يوجد تأخير
        }

        $shiftStart = strtotime($date . ' ' . $shiftInTime);
        $clockIn = strtotime($clockInTime);

        if ($clockIn <= $shiftStart) {
            return '00:00'; // حضر في الوقت أو قبله
        }

        // حساب التأخير
        $diffSeconds = $clockIn - $shiftStart;
        $hours = floor($diffSeconds / 3600);
        $minutes = floor(($diffSeconds % 3600) / 60);

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * Calculate early leaving (الخروج المبكر)
     * @param string $date Date (Y-m-d)
     * @param string $clockOutTime Clock out time (Y-m-d H:i:s)
     * @return string Early leaving time in format HH:MM or 00:00 if left on time or later
     */
    public function calculateEarlyLeaving(string $date, string $clockOutTime): string
    {
        $shiftOutTime = $this->getOutTimeForDate($date);

        if (empty($shiftOutTime)) {
            return '00:00'; // يوم عطلة
        }

        $shiftEnd = strtotime($date . ' ' . $shiftOutTime);
        $clockOut = strtotime($clockOutTime);

        if ($clockOut >= $shiftEnd) {
            return '00:00'; // انصرف في الوقت أو بعده
        }

        // حساب الخروج المبكر
        $diffSeconds = $shiftEnd - $clockOut;
        $hours = floor($diffSeconds / 3600);
        $minutes = floor(($diffSeconds % 3600) / 60);

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * Calculate overtime (الوقت الإضافي)
     * @param string $date Date (Y-m-d)
     * @param string $clockOutTime Clock out time (Y-m-d H:i:s)
     * @return string Overtime in format HH:MM or 00:00 if no overtime
     */
    public function calculateOvertime(string $date, string $clockOutTime): string
    {
        $shiftOutTime = $this->getOutTimeForDate($date);

        if (empty($shiftOutTime)) {
            return '00:00';
        }

        $shiftEnd = strtotime($date . ' ' . $shiftOutTime);
        $clockOut = strtotime($clockOutTime);

        if ($clockOut <= $shiftEnd) {
            return '00:00'; // لا يوجد وقت إضافي
        }

        // حساب الوقت الإضافي
        $diffSeconds = $clockOut - $shiftEnd;
        $hours = floor($diffSeconds / 3600);
        $minutes = floor(($diffSeconds % 3600) / 60);

        return sprintf('%02d:%02d', $hours, $minutes);
    }
}
