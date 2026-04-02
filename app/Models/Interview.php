<?php

namespace App\Models;

use App\Enums\Recruitment\InterviewStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interview extends Model
{
    use HasFactory;

    protected $table = 'ci_rec_interviews';
    protected $primaryKey = 'job_interview_id';
    public $timestamps = true;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'job_id',
        'designation_id',
        'staff_id',
        'interview_place',
        'interview_date',
        'interview_time',
        'interviewer_id',
        'description',
        'interview_remarks',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // 'status' => InterviewStatusEnum::class, // Handle manually
        'interview_date' => 'date',
        'company_id' => 'integer',
        'job_id' => 'integer',
        'designation_id' => 'integer',
        'staff_id' => 'integer',
        'interviewer_id' => 'integer',
    ];

    /**
     * Get the job associated with the interview.
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id', 'job_id');
    }

    /**
     * Get the candidate (recruitment application) being interviewed.
     * Links via staff_id and should match job_id.
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'staff_id', 'staff_id');
    }

    /**
     * Get the staff (User) being interviewed directly.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id', 'user_id');
    }
    
    // NOTE: Based on shared images, staff_id in interviews seems to link to the candidate or user. 
    // Usually, it links to Candidate. Let's assume it links to the Candidate model for now.

    /**
     * Get the interviewer (employee).
     */
    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id', 'user_id');
    }

    /**
     * Get the status enum from the value.
     */
    public function getStatusAttribute($value): ?InterviewStatusEnum
    {
        return InterviewStatusEnum::tryFrom((int)$value);
    }
}
