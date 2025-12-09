<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Designation extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'ci_designations';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'designation_id';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'department_id',
        'company_id',
        'hierarchy_level',
        'designation_name',
        'description',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'designation_id' => 'integer',
        'department_id' => 'integer',
        'company_id' => 'integer',
        'hierarchy_level' => 'integer',
    ];

    /**
     * Get the department that owns this designation.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    /**
     * Get the user details that have this designation.
     */
    public function userDetails(): HasMany
    {
        return $this->hasMany(UserDetails::class, 'designation_id', 'designation_id');
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to filter by department.
     */
    public function scopeForDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Get the name attribute (alias for designation_name).
     */
    public function getNameAttribute()
    {
        return $this->designation_name;
    }
}
