<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="WarehouseResource",
 *     title="WarehouseResource",
 *     description="Resource for Warehouse",
 *     @OA\Property(property="warehouse_id", type="integer", example=1),
 *     @OA\Property(property="warehouse_name", type="string", example="مستودع الرياض الرئيسى"),
 *     @OA\Property(property="contact_number", type="string", example="0501234567"),
 *     @OA\Property(property="pickup_location", type="integer", example=1),
 *     @OA\Property(property="address_1", type="string", example="شارع الملك فهد"),
 *     @OA\Property(property="address_2", type="string", example="حى العليا"),
 *     @OA\Property(property="city", type="string", example="الرياض"),
 *     @OA\Property(property="state", type="string", example="الرياض"),
 *     @OA\Property(property="zipcode", type="string", example="12345"),
 *     @OA\Property(property="country_id", type="integer", example=1),
 *     @OA\Property(property="status", type="integer", example=1),
 *     @OA\Property(property="added_by_name", type="string", example="أحمد محمد"),
 *     @OA\Property(property="created_at", type="string", format="datetime", example="2026-02-11 10:00:00")
 * )
 */
class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->warehouse_name,
            'contact_number' => $this->contact_number,
            'pickup_location' => $this->pickup_location,
            'address_1' => $this->address_1,
            'address_2' => $this->address_2,
            'city' => $this->city,
            'state' => $this->state,
            'zipcode' => $this->zipcode,
            'country_id' => $this->country,
            'status' => $this->status,
            'added_by_name' => $this->addedBy ? $this->addedBy->first_name . ' ' . $this->addedBy->last_name : null,
            'created_at' => $this->created_at,
        ];
    }
}
