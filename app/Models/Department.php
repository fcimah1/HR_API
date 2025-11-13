<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Department extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'ci_departments';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'department_id';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'department_name',
        'company_id',
        'department_head',
        'added_by',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'department_id' => 'integer',
        'company_id' => 'integer',
        'department_head' => 'integer',
        'added_by' => 'integer',
    ];

    /**
     * Get the user details that belong to this department.
     */
    public function userDetails(): HasMany
    {
        return $this->hasMany(UserDetails::class, 'department_id', 'department_id');
    }

    /**
     * Get the designations that belong to this department.
     */
    public function designations(): HasMany
    {
        return $this->hasMany(Designation::class, 'department_id', 'department_id');
    }

    /**
     * Get the department head user.
     */
    public function departmentHead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'department_head', 'user_id');
    }

    /**
     * Get the user who added this department.
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

    /**
     * Get the name attribute (alias for department_name).
     */
    public function getNameAttribute()
    {
        return $this->department_name;
    }
}
