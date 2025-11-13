<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Branch extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'ci_branchs';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'branch_id';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'branch_name',
        'branch_code',
        'company_id',
        'address',
        'phone',
        'email',
        'manager_id',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'branch_id' => 'integer',
        'company_id' => 'integer',
        'manager_id' => 'integer',
    ];

    /**
     * Get the company that owns this branch.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * Get the manager of this branch.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id', 'user_id');
    }

    /**
     * Get the user details that belong to this branch.
     */
    public function userDetails(): HasMany
    {
        return $this->hasMany(UserDetails::class, 'branch_id', 'branch_id');
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Get the name attribute (alias for branch_name).
     */
    public function getNameAttribute()
    {
        return $this->branch_name;
    }
}
