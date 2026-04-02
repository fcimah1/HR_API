<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $table = 'ci_stock_products';
    protected $primaryKey = 'product_id';
    public $timestamps = false; // Using manual created_at string in schema

    protected $attributes = [
        'product_rating' => 0,
    ];

    protected $fillable = [
        'company_id',
        'product_name',
        'product_qty',
        'reorder_stock',
        'barcode',
        'barcode_type',
        'warehouse_id',
        'category_id',
        'product_sku',
        'product_serial_number',
        'purchase_price',
        'retail_price',
        'expiration_date',
        'product_image',
        'product_description',
        'product_rating',
        'added_by',
        'status',
        'created_at',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'company_id' => 'integer',
        'product_qty' => 'integer',
        'reorder_stock' => 'integer',
        'warehouse_id' => 'integer',
        'category_id' => 'integer',
        'purchase_price' => 'float',
        'retail_price' => 'float',
        'product_rating' => 'integer',
        'added_by' => 'integer',
        'status' => 'boolean',
        'barcode_type' => \App\Enums\BarcodeTypeEnum::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(User::class, 'company_id', 'user_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by', 'user_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'warehouse_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ErpConstant::class, 'category_id', 'constants_id');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
