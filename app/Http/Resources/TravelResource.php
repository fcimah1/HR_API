<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
 *     @OA\Property(property="travel_type", type="integer", example=1),
 *     @OA\Property(property="travel_way", type="integer", example=2),
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
            'travel_type' => $this->travel_type,
            'travel_way' => $this->travel_way,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // يمكنك إضافة المزيد من الحقول حسب الحاجة
        ];
    }
}
