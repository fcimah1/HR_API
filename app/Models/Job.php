<?php

namespace App\Models;

use App\Enums\Recruitment\JobStatusEnum;
use App\Enums\JobTypeEnum;
use App\Enums\ExperienceLevel;
use App\Enums\GenderEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Job extends Model
{
    use HasFactory;

    protected $table = 'ci_rec_jobs';
    protected $primaryKey = 'job_id';
    public $timestamps = true; // Enable timestamps for created_at
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null; // Disable updated_at

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'job_title',
        'designation_id',
        'job_type',
        'job_vacancy',
        'gender',
        'minimum_experience',
        'date_of_closing',
        'short_description',
        'long_description',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // 'status' => JobStatusEnum::class, // Handle manually
        'date_of_closing' => 'date',
        'job_vacancy' => 'integer',
        'company_id' => 'integer',
        'designation_id' => 'integer',
        // 'job_type' => JobTypeEnum::class, // Handle manually to ensure string casting
        // 'gender' => GenderEnum::class, // Handle manually to avoid ValueError on legacy data
        // 'minimum_experience' => ExperienceLevel::class, // Handle manually due to Type mismatch (DB String vs Enum Int)
    ];

    /**
     * Get the designation associated with the job.
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation_id', 'designation_id');
    }

    /**
     * Get the experience level enum from the integer value.
     */
    public function getMinimumExperienceAttribute($value): ?ExperienceLevel
    {
        return ExperienceLevel::tryFrom((int)$value);
    }

    /**
     * Get the gender enum from the value.
     */
    public function getGenderAttribute($value): ?GenderEnum
    {
        return is_numeric($value) ? GenderEnum::tryFrom((int)$value) : null;
    }

    /**
     * Get the job type enum from the value.
     */
    public function getJobTypeAttribute($value): ?JobTypeEnum
    {
        return is_numeric($value) ? JobTypeEnum::tryFrom((int)$value) : null;
    }

    /**
     * Get the status enum from the value.
     */
    public function getStatusAttribute($value): ?JobStatusEnum
    {
        return is_numeric($value) ? JobStatusEnum::tryFrom((int)$value) : null;
    }
}
