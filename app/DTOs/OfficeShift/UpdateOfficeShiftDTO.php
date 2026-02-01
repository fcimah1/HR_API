<?php

namespace App\DTOs\OfficeShift;

class UpdateOfficeShiftDTO
{
    public function __construct(
        public readonly int $officeShiftId,
        public readonly ?string $shiftName = null,
        public readonly ?string $mondayInTime = null,
        public readonly ?string $mondayOutTime = null,
        public readonly ?string $tuesdayInTime = null,
        public readonly ?string $tuesdayOutTime = null,
        public readonly ?string $wednesdayInTime = null,
        public readonly ?string $wednesdayOutTime = null,
        public readonly ?string $thursdayInTime = null,
        public readonly ?string $thursdayOutTime = null,
        public readonly ?string $fridayInTime = null,
        public readonly ?string $fridayOutTime = null,
        public readonly ?string $saturdayInTime = null,
        public readonly ?string $saturdayOutTime = null,
        public readonly ?string $sundayInTime = null,
        public readonly ?string $sundayOutTime = null,
        public readonly ?string $mondayLunchBreak = null,
        public readonly ?string $tuesdayLunchBreak = null,
        public readonly ?string $wednesdayLunchBreak = null,
        public readonly ?string $thursdayLunchBreak = null,
        public readonly ?string $fridayLunchBreak = null,
        public readonly ?string $saturdayLunchBreak = null,
        public readonly ?string $sundayLunchBreak = null,
        public readonly ?string $mondayLunchBreakOut = null,
        public readonly ?string $tuesdayLunchBreakOut = null,
        public readonly ?string $wednesdayLunchBreakOut = null,
        public readonly ?string $thursdayLunchBreakOut = null,
        public readonly ?string $fridayLunchBreakOut = null,
        public readonly ?string $saturdayLunchBreakOut = null,
        public readonly ?string $sundayLunchBreakOut = null,
        public readonly ?int $hoursPerDay = null,
        public readonly ?string $inTimeBeginning = null,
        public readonly ?string $inTimeEnd = null,
        public readonly ?int $lateAllowance = null,
        public readonly ?string $outTimeBeginning = null,
        public readonly ?string $outTimeEnd = null,
        public readonly ?string $breakStart = null,
        public readonly ?string $breakEnd = null,
    ) {}

    public static function fromRequest(array $data, int $id): self
    {
        return new self(
            officeShiftId: $id,
            shiftName: $data['shift_name'] ?? null,
            mondayInTime: $data['monday_in_time'] ?? null,
            mondayOutTime: $data['monday_out_time'] ?? null,
            tuesdayInTime: $data['tuesday_in_time'] ?? null,
            tuesdayOutTime: $data['tuesday_out_time'] ?? null,
            wednesdayInTime: $data['wednesday_in_time'] ?? null,
            wednesdayOutTime: $data['wednesday_out_time'] ?? null,
            thursdayInTime: $data['thursday_in_time'] ?? null,
            thursdayOutTime: $data['thursday_out_time'] ?? null,
            fridayInTime: $data['friday_in_time'] ?? null,
            fridayOutTime: $data['friday_out_time'] ?? null,
            saturdayInTime: $data['saturday_in_time'] ?? null,
            saturdayOutTime: $data['saturday_out_time'] ?? null,
            sundayInTime: $data['sunday_in_time'] ?? null,
            sundayOutTime: $data['sunday_out_time'] ?? null,
            mondayLunchBreak: $data['monday_lunch_break'] ?? null,
            tuesdayLunchBreak: $data['tuesday_lunch_break'] ?? null,
            wednesdayLunchBreak: $data['wednesday_lunch_break'] ?? null,
            thursdayLunchBreak: $data['thursday_lunch_break'] ?? null,
            fridayLunchBreak: $data['friday_lunch_break'] ?? null,
            saturdayLunchBreak: $data['saturday_lunch_break'] ?? null,
            sundayLunchBreak: $data['sunday_lunch_break'] ?? null,
            mondayLunchBreakOut: $data['monday_lunch_break_out'] ?? null,
            tuesdayLunchBreakOut: $data['tuesday_lunch_break_out'] ?? null,
            wednesdayLunchBreakOut: $data['wednesday_lunch_break_out'] ?? null,
            thursdayLunchBreakOut: $data['thursday_lunch_break_out'] ?? null,
            fridayLunchBreakOut: $data['friday_lunch_break_out'] ?? null,
            saturdayLunchBreakOut: $data['saturday_lunch_break_out'] ?? null,
            sundayLunchBreakOut: $data['sunday_lunch_break_out'] ?? null,
            hoursPerDay: isset($data['hours_per_day']) ? (int) $data['hours_per_day'] : null,
            inTimeBeginning: $data['in_time_beginning'] ?? null,
            inTimeEnd: $data['in_time_end'] ?? null,
            lateAllowance: isset($data['late_allowance']) ? (int) $data['late_allowance'] : null,
            outTimeBeginning: $data['out_time_beginning'] ?? null,
            outTimeEnd: $data['out_time_end'] ?? null,
            breakStart: $data['break_start'] ?? null,
            breakEnd: $data['break_end'] ?? null,
        );
    }

    public function toArray(): array
    {
        $data = [];
        foreach ($this as $key => $value) {
            if ($value !== null && $key !== 'officeShiftId') {
                $snakeKey = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
                $data[$snakeKey] = $value;
            }
        }
        return $data;
    }
}
