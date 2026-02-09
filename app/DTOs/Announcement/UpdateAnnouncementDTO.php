<?php

namespace App\DTOs\Announcement;

class UpdateAnnouncementDTO
{
    public function __construct(
        public readonly ?string $departmentId = null,
        public readonly ?string $audienceId = null,
        public readonly ?string $title = null,
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null,
        public readonly ?string $summary = null,
        public readonly ?string $description = null,
        public readonly ?bool $isActive = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        $departmentId = $data['department_id'] ?? null;
        if (is_array($departmentId)) {
            $departmentId = implode(',', $departmentId);
        }

        $audienceId = $data['audience_id'] ?? null;
        if (is_array($audienceId)) {
            $audienceId = implode(',', $audienceId);
        }

        return new self(
            departmentId: $departmentId,
            audienceId: $audienceId,
            title: $data['title'] ?? null,
            startDate: $data['start_date'] ?? null,
            endDate: $data['end_date'] ?? null,
            summary: $data['summary'] ?? null,
            description: $data['description'] ?? null,
            isActive: isset($data['is_active']) ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'department_id' => $this->departmentId,
            'audience_id' => $this->audienceId,
            'title' => $this->title,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'summary' => $this->summary,
            'description' => $this->description,
            'is_active' => $this->isActive !== null ? ($this->isActive ? 1 : 0) : null,
        ], fn($value) => $value !== null);
    }
}
