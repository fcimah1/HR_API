<?php

namespace App\DTOs\Recruitment\Candidate;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

class UpdateCandidateDTO extends Data
{
    public function __construct(
        public ?int $company_id = null,
        public ?int $job_id = null,
        public ?int $designation_id = null,
        public ?int $staff_id = null,
        public ?string $message = null,
        public ?UploadedFile $job_resume_file = null,
        public ?int $application_status = null,
        public ?string $application_remarks = null,
    ) {}
}
