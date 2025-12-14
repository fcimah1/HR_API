<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Suggestion extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'ci_suggestions';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'suggestion_id';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'title',
        'description',
        'attachment',
        'added_by',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'company_id' => 'integer',
        'added_by' => 'integer',
    ];

    /**
     * Get the employee who created the suggestion.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(UserDetails::class, 'added_by', 'user_id');
    }

    /**
     * Get the comments for the suggestion.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(SuggestionComment::class, 'suggestion_id', 'suggestion_id');
    }
}
