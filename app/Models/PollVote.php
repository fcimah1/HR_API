<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PollVote extends Model
{
    use HasFactory;

    protected $table = 'ci_polls_votes';
    protected $primaryKey = 'polls_vote_id';
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'poll_id',
        'poll_question_id',
        'poll_answer',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'poll_id' => 'integer',
        'poll_question_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class, 'poll_id', 'poll_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(PollQuestion::class, 'poll_question_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
