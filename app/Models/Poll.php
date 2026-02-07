<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poll extends Model
{
    use HasFactory;

    protected $table = 'ci_polls';
    protected $primaryKey = 'poll_id';
    public $timestamps = false; // Based on schema having only created_at usually, but let's check if updated_at exists. Schema said created_at only.

    protected $fillable = [
        'company_id',
        'poll_title',
        'poll_start_date',
        'poll_end_date',
        'added_by',
        'is_active',
        'created_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'added_by' => 'integer',
        'is_active' => 'boolean',
        'poll_start_date' => 'date',
        'poll_end_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by', 'user_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(PollQuestion::class, 'poll_ref_id', 'poll_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class, 'poll_id', 'poll_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1)
            ->whereDate('poll_start_date', '<=', now())
            ->whereDate('poll_end_date', '>=', now());
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
