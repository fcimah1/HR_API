<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="TaxTypeResource",
 *     title="Tax Type Resource",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="tax_name", type="string"),
 *     @OA\Property(property="tax_rate", type="string"),
 *     @OA\Property(property="tax_type", type="string")
 * )
 */
class TaxTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->constants_id,
            'tax_name' => $this->category_name,
            'tax_rate' => $this->field_one,
            'tax_type' => $this->field_two,
        ];
    }
}
