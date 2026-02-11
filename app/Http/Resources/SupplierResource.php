<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SupplierResource",
 *     title="SupplierResource",
 *     description="Resource for Supplier",
 *     @OA\Property(property="supplier_id", type="integer", example=1),
 *     @OA\Property(property="supplier_name", type="string", example="شركة توريدات الخليج"),
 *     @OA\Property(property="registration_no", type="string", example="CR12345678"),
 *     @OA\Property(property="email", type="string", example="info@gulf-supplies.com"),
 *     @OA\Property(property="contact_number", type="string", example="0556543210"),
 *     @OA\Property(property="website_url", type="string", example="https://gulf-supplies.com"),
 *     @OA\Property(property="address_1", type="string", example="المنطقة الصناعية"),
 *     @OA\Property(property="address_2", type="string", example="بلوك 14"),
 *     @OA\Property(property="city", type="string", example="جدة"),
 *     @OA\Property(property="state", type="string", example="مكة المكرمة"),
 *     @OA\Property(property="zipcode", type="string", example="54321"),
 *     @OA\Property(property="country_id", type="integer", example=1),
 *     @OA\Property(property="added_by_name", type="string", example="أحمد محمد"),
 *     @OA\Property(property="created_at", type="string", format="datetime", example="2026-02-11 10:00:00")
 * )
 */
class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier_name,
            'registration_no' => $this->registration_no,
            'email' => $this->email,
            'contact_number' => $this->contact_number,
            'website_url' => $this->website_url,
            'address_1' => $this->address_1,
            'address_2' => $this->address_2,
            'city' => $this->city,
            'state' => $this->state,
            'zipcode' => $this->zipcode,
            'country_id' => $this->country,
            'added_by_name' => $this->addedBy ? $this->addedBy->first_name . ' ' . $this->addedBy->last_name : null,
            'created_at' => $this->created_at,
        ];
    }
}
