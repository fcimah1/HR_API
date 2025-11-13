<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficeShift extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'ci_office_shifts';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'office_shift_id';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'shift_name',
        'monday_in_time',
        'monday_out_time',
        'tuesday_in_time',
        'tuesday_out_time',
        'wednesday_in_time',
        'wednesday_out_time',
        'thursday_in_time',
        'thursday_out_time',
        'friday_in_time',
        'friday_out_time',
        'saturday_in_time',
        'saturday_out_time',
        'sunday_in_time',
        'sunday_out_time',
        'hours_per_day',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'office_shift_id' => 'integer',
        'company_id' => 'integer',
        'hours_per_day' => 'integer',
        'monday_in_time' => 'string',
        'monday_out_time' => 'string',
        'tuesday_in_time' => 'string',
        'tuesday_out_time' => 'string',
        'wednesday_in_time' => 'string',
        'wednesday_out_time' => 'string',
        'thursday_in_time' => 'string',
        'thursday_out_time' => 'string',
        'friday_in_time' => 'string',
        'friday_out_time' => 'string',
        'saturday_in_time' => 'string',
        'saturday_out_time' => 'string',
        'sunday_in_time' => 'string',
        'sunday_out_time' => 'string',
    ];

    /**
     * Get the user details that have this office shift.
     */
    public function userDetails(): HasMany
    {
        return $this->hasMany(UserDetails::class, 'office_shift_id', 'office_shift_id');
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Get the name attribute (alias for shift_name).
     */
    public function getNameAttribute()
    {
        return $this->shift_name;
    }
}
