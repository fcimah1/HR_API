<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Trainer Model
 * نموذج المدرب
 */
class Trainer extends Model
{
    protected $table = 'ci_trainers';
    protected $primaryKey = 'trainer_id';
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'first_name',
        'last_name',
        'contact_number',
        'email',
        'expertise',
        'address',
        'created_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'trainer_id' => 'integer',
    ];

    // ==================== Relationships ====================

    /**
     * Get trainings conducted by this trainer
     */
    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class, 'trainer_id', 'trainer_id');
    }

    // ==================== Scopes ====================

    /**
     * Scope for company isolation
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope for searching by name
     */
    public function scopeSearchByName($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%");
        });
    }

    /**
     * Scope for searching by email
     */
    public function scopeSearchByEmail($query, string $email)
    {
        return $query->where('email', 'like', "%{$email}%");
    }

    // ==================== Accessors ====================

    /**
     * Get full name
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get trainings count
     */
    public function getTrainingsCountAttribute(): int
    {
        return $this->trainings()->count();
    }
}
