<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffRole extends Model
{
    protected $table = 'ci_staff_roles';
    protected $primaryKey = 'role_id';
    public $timestamps = false;

    protected $fillable = [
        'role_id',
        'company_id',
        'role_name',
        'role_access',
        'role_resources',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'role_id' => 'integer',
        'company_id' => 'integer',
        'role_access' => 'integer',
    ];

    /**
     * Get users with this role
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'user_role_id', 'role_id')
            ->where('company_id', $this->company_id);
    }

    /**
     * Check if role has specific permission
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = array_filter(explode(',', $this->role_resources ?? ''));
        return in_array($permission, $permissions);
    }

    /**
     * Get all permissions for this role
     */
    public function getPermissions(): array
    {
        return array_filter(explode(',', $this->role_resources ?? ''));
    }

    /**
     * Scope for filtering by company
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
