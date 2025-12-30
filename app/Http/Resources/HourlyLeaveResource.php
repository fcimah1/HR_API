<?php

namespace App\Http\Resources;

use App\Enums\DeductedStatus;
use App\Enums\LeavePlaceEnum;
use App\Enums\NumericalStatusEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HourlyLeaveResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'leave_id' => $this->leave_id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->when(
                $this->relationLoaded('employee'),
                fn() => $this->employee ? ($this->employee->first_name . ' ' . $this->employee->last_name) : 'غير محدد'
            ),
            'leave_type_id' => $this->leave_type_id,
            'leave_type_name' => $this->when(
                $this->relationLoaded('leaveType'),
                fn() => $this->leaveType ? $this->leaveType->category_name : 'غير محدد'
            ),
            'clock_in_m' => $this->from_date,
            'clock_out_m' => $this->to_date,
            'particular_date' => $this->particular_date,
            'leave_hours' => $this->leave_hours,
            'leave_month' => $this->leave_month,
            'leave_year' => $this->leave_year,
            'reason' => $this->reason,
            'duty_employee_id' => $this->duty_employee_id,
            'duty_employee_name' => $this->when(
                $this->relationLoaded('dutyEmployee') && $this->dutyEmployee,
                fn() => $this->dutyEmployee->first_name . ' ' . $this->dutyEmployee->last_name
            ),
            'remarks' => $this->remarks,
            'is_half_day' => $this->is_half_day,
            'is_deducted' => $this->is_deducted,
            'is_deducted_text' => $this->is_deducted ? DeductedStatus::DEDUCTED->labelAr() : DeductedStatus::NOT_DEDUCTED->labelAr(),
            'place' => $this->place,
            'place_text' => $this->place ? LeavePlaceEnum::INSIDE->labelAr() : LeavePlaceEnum::OUTSIDE->labelAr(),
            'status' => $this->status,
            'status_text' => $this->getStatusText($this->status),
            'created_at' => $this->created_at,
            
            // معلومات الموظف إذا كانت محملة
            'employee' => $this->when($this->relationLoaded('employee'), function () {
                return $this->employee ? [
                    'user_id' => $this->employee->user_id,
                    'first_name' => $this->employee->first_name,
                    'last_name' => $this->employee->last_name,
                    'email' => $this->employee->email,
                    'full_name' => $this->employee->full_name,
                    'department' => $this->employee->user_details?->department?->name ?? null,
                    'position' => $this->employee->user_details?->designation?->name ?? null,
                ] : null;
            }),
            
            // معلومات الموظف البديل إذا كانت محملة
            'duty_employee' => $this->when($this->relationLoaded('dutyEmployee'), function () {
                return $this->dutyEmployee ? [
                    'user_id' => $this->dutyEmployee->user_id,
                    'first_name' => $this->dutyEmployee->first_name,
                    'last_name' => $this->dutyEmployee->last_name,
                    'email' => $this->dutyEmployee->email,
                    'full_name' => $this->dutyEmployee->full_name,
                    'department' => $this->dutyEmployee->user_details?->department?->name ?? null,
                    'position' => $this->dutyEmployee->user_details?->designation?->name ?? null,
                ] : null;
            }),
            
            // معلومات نوع الإجازة إذا كانت محملة
            'leave_type' => $this->when($this->relationLoaded('leaveType'), function () {
                return $this->leaveType ? [
                    'constants_id' => $this->leaveType->constants_id,
                    'category_name' => $this->leaveType->category_name,
                ] : null;
            }),
            
            // معلومات الموافقات إذا كانت محملة
            'approvals' => $this->when($this->relationLoaded('approvals'), function () {
                return $this->approvals->map(function ($approval) {
                    return [
                        'staff_approval_id' => $approval->staff_approval_id,
                        'staff_id' => $approval->staff_id,
                        'staff_name' => $approval->staff ? $approval->staff->full_name : null,
                        'department' => $approval->user_details?->department?->name ?? null,
                        'position' => $approval->user_details?->designation?->name ?? null,
                        'status' => $approval->status,
                        'approval_level' => $approval->approval_level,
                        'updated_at' => $approval->updated_at,
                    ];
                });
            }),
        ];
    }

    /**
     * الحصول على نص الحالة
     */
    private function getStatusText($status): string
    {
        return match ($status) {
            NumericalStatusEnum::PENDING->value => 'pending',
            NumericalStatusEnum::APPROVED->value => 'approved',
            NumericalStatusEnum::REJECTED->value => 'rejected',
            default => 'pending',
        };
    }
}

