<?php

namespace App\DTOs\Announcement;

class CreateAnnouncementDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly string $departmentId,
        public readonly ?string $audienceId,
        public readonly string $title,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly int $publishedBy,
        public readonly string $summary,
        public readonly string $description,
        public readonly bool $isActive = true,
    ) {}

    public static function fromRequest(array $data, int $companyId, int $userId): self
    {
        $departmentId = $data['department_id'] ?? '0,all';
        if (is_array($departmentId)) {
            $departmentId = implode(',', $departmentId);
        }

        $audienceId = $data['audience_id'] ?? '0,all';
        if (is_array($audienceId)) {
            $audienceId = implode(',', $audienceId);
        }

        return new self(
            companyId: $companyId,
            departmentId: $departmentId ?: '0,all',
            audienceId: $audienceId ?: '0,all',
            title: $data['title'],
            startDate: $data['start_date'],
            endDate: $data['end_date'],
            publishedBy: $userId,
            summary: $data['summary'],
            description: $data['description'],
            isActive: filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'department_id' => $this->departmentId,
            'audience_id' => $this->audienceId,
            'title' => $this->title,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'published_by' => $this->publishedBy,
            'summary' => $this->summary,
            'description' => $this->description,
            'is_active' => $this->isActive ? 1 : 0,
        ];
    }
}
