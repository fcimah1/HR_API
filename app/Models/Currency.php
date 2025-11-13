<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'ci_currencies';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'currency_id';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'currency_name',
        'currency_code',
        'currency_symbol',
        'exchange_rate',
        'is_default',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'currency_id' => 'integer',
        'exchange_rate' => 'decimal:4',
        'is_default' => 'boolean',
    ];

    /**
     * Get the user details that use this currency.
     */
    public function userDetails(): HasMany
    {
        return $this->hasMany(UserDetails::class, 'currency_id', 'currency_id');
    }

    /**
     * Scope to get default currency.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get the name attribute (alias for currency_name).
     */
    public function getNameAttribute()
    {
        return $this->currency_name;
    }
}
