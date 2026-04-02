<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PollQuestion extends Model
{
    use HasFactory;

    protected $table = 'ci_polls_questions';
    protected $primaryKey = 'id';
    public $timestamps = false; // Schema didn't mention timestamps for this table

    protected $fillable = [
        'poll_ref_id',
        'company_id',
        'poll_question',
        'poll_answer1',
        'poll_answer2',
        'poll_answer3',
        'poll_answer4',
        'poll_answer5',
        'notes',
    ];

    protected $casts = [
        'poll_ref_id' => 'integer',
        'company_id' => 'integer',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class, 'poll_ref_id', 'poll_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class, 'poll_question_id', 'id');
    }
}
