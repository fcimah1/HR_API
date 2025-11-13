<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'ci_countries';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'country_id';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'country_name',
        'country_code',
        'iso_code',
        'phone_code',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'country_id' => 'integer',
    ];

    /**
     * Get the user details that belong to this country.
     */
    public function userDetails(): HasMany
    {
        return $this->hasMany(UserDetails::class, 'country_id', 'country_id');
    }

    /**
     * Get the name attribute (alias for country_name).
     */
    public function getNameAttribute()
    {
        return $this->country_name;
    }
}
