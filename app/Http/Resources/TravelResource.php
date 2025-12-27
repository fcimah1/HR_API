<?php

namespace App\Http\Resources;

use App\Enums\NumericalStatusEnum;
use App\Enums\TravelModeEnum;
use App\Enums\TravelStatusEnum;
use App\Models\Travel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Traversable;

/**
 * @OA\Schema(
 *     schema="TravelResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="employee_id", type="integer", example=123),
 *     @OA\Property(property="employee_name", type="string", example="John Doe"),
 *     @OA\Property(property="start_date", type="string", format="date", example="2025-01-01"),
 *     @OA\Property(property="end_date", type="string", format="date", example="2025-01-05"),
 *     @OA\Property(property="visit_place", type="string", example="New York"),
 *     @OA\Property(property="visit_purpose", type="string", example="Business meeting"),
 *     @OA\Property(property="travel_mode", type="integer", example=1),
 *     @OA\Property(property="arrangement_type", type="integer", example=1),
 *     @OA\Property(property="description", type="string", example="وصف الرحلة"),
 *     @OA\Property(property="status", type="integer", example=0),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

class TravelResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->travel_id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee->full_name ?? null,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'visit_place' => $this->visit_place,
            'visit_purpose' => $this->visit_purpose,
            'travel_mode' => $this->travel_mode,
            'travel_mode_name' => TravelModeEnum::tryFrom($this->travel_mode)?->label() ?? 'Unknown',
            'arrangement_type' => $this->arrangement_type,
            'arrangement_type_name' => Travel::arrangementTypeName($this->arrangement_type) ?? 'Unknown',
            'description' => $this->description,
            'status' => $this->status,
            'status_name' => TravelStatusEnum::tryFrom($this->status)?->labelAr() ?? 'Unknown',
            'associated_goals' => $this->associated_goals,
            'added_by' => $this->added_by,
            'added_by_name' => $this->addedBy->full_name ?? null,
            'created_at' => $this->created_at,

            // معلومات الموظف إذا كانت محملة
            'employee' => $this->when($this->relationLoaded('employee'), function () {
                if (!$this->employee) return null;

                $firstName = $this->employee->first_name ?? '';
                $lastName = $this->employee->last_name ?? '';
                $fullName = trim($firstName . ' ' . $lastName);

                return [
                    'user_id' => $this->employee->user_id,
                    'first_name' => $firstName ?: null,
                    'last_name' => $lastName ?: null,
                    'email' => $this->employee->email,
                    'full_name' => $fullName ?: 'غير محدد',
                    'department' => $this->employee->user_details?->department?->name ?? null,
                    'position' => $this->employee->user_details?->designation?->name ?? null,
                ];
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
}
