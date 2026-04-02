<?php

namespace App\DTOs\Recruitment\Job;

use Spatie\LaravelData\Data;

class CreateJobDTO extends Data
{
    public function __construct(
        public int $company_id,
        public string $job_title,
        public int $designation_id,
        public mixed $job_type,
        public int $job_vacancy,
        public mixed $gender,
        public mixed $minimum_experience,
        public string $date_of_closing,
        public ?string $short_description,
        public ?string $long_description,
        public mixed $status,
    ) {}
}
