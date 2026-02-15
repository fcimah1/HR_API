<?php

namespace App\DTOs\Recruitment\Interview;

use Spatie\LaravelData\Data;

class CreateInterviewDTO extends Data
{
    public function __construct(
        public int $company_id,
        public int $job_id,
        public int $designation_id,
        public int $staff_id, // Candidate ID (from ci_rec_interviews schema staff_id maps to candidate)
        public string $interview_place,
        public string $interview_date,
        public string $interview_time,
        public int $interviewer_id,
        public string $description,
        public ?string $interview_remarks,
        public int $status,
    ) {}
}
