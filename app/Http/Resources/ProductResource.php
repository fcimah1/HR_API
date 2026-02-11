<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="ProductResource",
 *     title="Product Resource",
 *     @OA\Property(property="product_id", type="integer"),
 *     @OA\Property(property="product_name", type="string"),
 *     @OA\Property(property="product_qty", type="integer"),
 *     @OA\Property(property="reorder_stock", type="integer"),
 *     @OA\Property(property="barcode", type="string"),
 *     @OA\Property(property="barcode_type", type="string"),
 *     @OA\Property(property="warehouse_name", type="string"),
 *     @OA\Property(property="category_name", type="string"),
 *     @OA\Property(property="product_sku", type="string"),
 *     @OA\Property(property="product_serial_number", type="string"),
 *     @OA\Property(property="purchase_price", type="number", format="float"),
 *     @OA\Property(property="retail_price", type="number", format="float"),
 *     @OA\Property(property="expiration_date", type="string"),
 *     @OA\Property(property="product_image", type="string"),
 *     @OA\Property(property="product_description", type="string"),
 *     @OA\Property(property="product_rating", type="integer"),
 *     @OA\Property(property="added_by_name", type="string"),
 *     @OA\Property(property="created_at", type="string"),
 *     @OA\Property(property="status", type="boolean")
 * )
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_qty' => $this->product_qty,
            'reorder_stock' => $this->reorder_stock,
            'barcode' => $this->barcode,
            'barcode_type' => $this->barcode_type,
            'warehouse_name' => $this->warehouse?->warehouse_name ?? '--',
            'category_name' => $this->category?->category_name ?? '--',
            'product_sku' => $this->product_sku,
            'product_serial_number' => $this->product_serial_number,
            'purchase_price' => $this->purchase_price,
            'retail_price' => $this->retail_price,
            'expiration_date' => $this->expiration_date,
            'product_image' => $this->product_image ? 'products/' . $this->product_image : null,
            'product_description' => $this->product_description,
            'product_rating' => $this->product_rating,
            'added_by_name' => $this->addedBy?->first_name . ' ' . $this->addedBy?->last_name,
            'created_at' => $this->created_at,
            'status' => $this->status,
        ];
    }
}
