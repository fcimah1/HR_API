<?php

namespace App\DTOs\Recruitment\Job;

use Spatie\LaravelData\Data;

class UpdateJobDTO extends Data
{
    public function __construct(
        public ?int $company_id = null,
        public ?string $job_title = null,
        public ?int $designation_id = null,
        public mixed $job_type = null,
        public ?int $job_vacancy = null,
        public mixed $gender = null,
        public mixed $minimum_experience = null,
        public ?string $date_of_closing = null,
        public ?string $short_description = null,
        public ?string $long_description = null,
        public mixed $status = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            company_id: $data['company_id'] ?? null,
            job_title: $data['job_title'] ?? null,
            designation_id: $data['designation_id'] ?? null,
            job_type: $data['job_type'] ?? null,
            job_vacancy: $data['job_vacancy'] ?? null,
            gender: $data['gender'] ?? null,
            minimum_experience: $data['minimum_experience'] ?? null,
            date_of_closing: (isset($data['date_of_closing']) && $data['date_of_closing'] instanceof \DateTimeInterface)
                ? $data['date_of_closing']->format('Y-m-d')
                : ($data['date_of_closing'] ?? null),
            short_description: $data['short_description'] ?? null,
            long_description: $data['long_description'] ?? null,
            status: $data['status'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->company_id,
            'job_title' => $this->job_title,
            'designation_id' => $this->designation_id,
            'job_type' => $this->job_type,
            'job_vacancy' => $this->job_vacancy,
            'gender' => $this->gender,
            'minimum_experience' => $this->minimum_experience,
            'date_of_closing' => $this->date_of_closing,
            'short_description' => $this->short_description,
            'long_description' => $this->long_description,
            'status' => $this->status,
        ];
    }
}
