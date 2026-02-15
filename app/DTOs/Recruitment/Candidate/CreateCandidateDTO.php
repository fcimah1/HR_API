<?php

namespace App\DTOs\Recruitment\Candidate;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

class CreateCandidateDTO extends Data
{
    public function __construct(
        public int $company_id,
        public int $job_id,
        public int $designation_id,
        public ?int $staff_id,
        public string $message,
        public ?UploadedFile $job_resume_file, // Holds the file object
        public int $application_status,
        public ?string $application_remarks,
    ) {}
}
