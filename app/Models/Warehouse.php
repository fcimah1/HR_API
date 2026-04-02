<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warehouse extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'ci_stock_warehouses';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'warehouse_id';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'warehouse_name',
        'contact_number',
        'pickup_location',
        'address_1',
        'address_2',
        'city',
        'state',
        'zipcode',
        'country',
        'added_by',
        'status',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'warehouse_id' => 'integer',
        'company_id' => 'integer',
        'pickup_location' => 'integer',
        'country' => 'integer',
        'added_by' => 'integer',
        'status' => 'integer',
    ];



    /**
     * Get the user who added this warehouse.
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by', 'user_id');
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
