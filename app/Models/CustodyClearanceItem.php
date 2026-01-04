<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $item_id
 * @property int $clearance_id
 * @property int $asset_id
 * @property string|null $asset_condition
 * @property string|null $return_date
 * @property string|null $notes
 * @property string $created_at
 */
class CustodyClearanceItem extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'ci_custody_clearance_items';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'item_id';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'clearance_id',
        'asset_id',
        'asset_condition',
        'return_date',
        'notes',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'clearance_id' => 'integer',
        'asset_id' => 'integer',
        'return_date' => 'date',
        'created_at' => 'datetime',
    ];

    /**
     * Get the custody clearance that owns this item.
     */
    public function clearance(): BelongsTo
    {
        return $this->belongsTo(CustodyClearance::class, 'clearance_id', 'clearance_id');
    }

    /**
     * Get the asset associated with this item.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id', 'assets_id');
    }

    /**
     * Scope for a specific clearance.
     */
    public function scopeForClearance($query, int $clearanceId)
    {
        return $query->where('clearance_id', $clearanceId);
    }
}
