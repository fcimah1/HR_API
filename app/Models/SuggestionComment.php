<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuggestionComment extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'ci_suggestion_comments';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'comment_id';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'suggestion_id',
        'employee_id',
        'suggestion_comment',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'company_id' => 'integer',
        'suggestion_id' => 'integer',
        'employee_id' => 'integer',
    ];

    /**
     * Get the suggestion this comment belongs to.
     */
    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(Suggestion::class, 'suggestion_id', 'suggestion_id');
    }

    /**
     * Get the employee who wrote the comment.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }
}
