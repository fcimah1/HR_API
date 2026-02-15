<?php

namespace App\Models;

use App\Enums\Recruitment\CandidateStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Candidate extends Model
{
    use HasFactory;

    protected $table = 'ci_rec_candidates';
    protected $primaryKey = 'candidate_id';
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
        'message',
        'job_resume',
        'application_status',
        'application_remarks',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'application_status' => CandidateStatusEnum::class,
        'company_id' => 'integer',
        'job_id' => 'integer',
        'designation_id' => 'integer',
        'staff_id' => 'integer',
    ];

    /**
     * Get the job associated with the candidate.
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id', 'job_id');
    }

    /**
     * Get the designation associated with the candidate.
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation_id', 'designation_id');
    }

    /**
     * Get the staff (employee) if the candidate is an internal employee.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id', 'user_id'); // Assuming User model is used for staff
    }

    /**
     * Get the job resume with prefix.
     */
    public function getJobResumeAttribute($value): ?string
    {
        return $value ? 'candidates/' . $value : $value;
    }
}
