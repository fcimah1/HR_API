<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Supplier extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'ci_stock_suppliers';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'supplier_id';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'supplier_name',
        'registration_no',
        'email',
        'contact_number',
        'website_url',
        'address_1',
        'address_2',
        'city',
        'state',
        'zipcode',
        'country',
        'added_by',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'supplier_id' => 'integer',
        'company_id' => 'integer',
        'country' => 'integer',
        'added_by' => 'integer',
    ];

   
    /**
     * Get the user who added this supplier.
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
