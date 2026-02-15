<?php

namespace App\DTOs\Recruitment\Interview;

use Spatie\LaravelData\Data;

class UpdateInterviewDTO extends Data
{
    public function __construct(
        public ?int $company_id = null,
        public ?int $job_id = null,
        public ?int $designation_id = null,
        public ?int $staff_id = null,
        public ?string $interview_place = null,
        public ?string $interview_date = null,
        public ?string $interview_time = null,
        public ?int $interviewer_id = null,
        public ?string $description = null,
        public ?string $interview_remarks = null,
        public ?int $status = null,
    ) {}
}
