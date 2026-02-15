<?php

namespace App\DTOs\Recruitment\Job;

use Spatie\LaravelData\Data;
use Illuminate\Http\UploadedFile;

class ApplyJobDTO extends Data
{
    public function __construct(
        public int $job_id,
        public int $company_id,
        public int $designation_id,
        public ?int $staff_id,
        public ?string $message = null,
        public ?UploadedFile $job_resume = null,
    ) {}
}
