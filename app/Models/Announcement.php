<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    protected $table = 'ci_announcements';
    protected $primaryKey = 'announcement_id';

    // Using varchar for created_at, so disable automatic timestamps
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'department_id',
        'audience_id',
        'title',
        'start_date',
        'end_date',
        'published_by',
        'summary',
        'description',
        'is_active',
        'created_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'company_id' => 'integer',
        'published_by' => 'integer'
    ];

    /**
     * Get the staff member who published the announcement.
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by', 'user_id');
    }

    /**
     * Scope a query to only include announcements for a specific company.
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
