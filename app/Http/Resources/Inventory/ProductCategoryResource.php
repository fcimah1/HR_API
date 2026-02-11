<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="ProductCategoryResource",
 *     title="Product Category Resource",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="category_name", type="string")
 * )
 */
class ProductCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->constants_id,
            'category_name' => $this->category_name,
        ];
    }
}
