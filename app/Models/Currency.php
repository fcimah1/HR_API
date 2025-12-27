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
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'country_name',
        'currency_name',
        'currency_code',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'currency_id' => 'integer',
    ];

    /**
     * Get the user details that use this currency.
     */
    public function userDetails(): HasMany
    {
        return $this->hasMany(UserDetails::class, 'currency_id', 'currency_id');
    }

    /**
     * Get the name attribute (alias for currency_name).
     */
    public function getNameAttribute()
    {
        return $this->currency_name;
    }
}
