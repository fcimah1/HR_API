<?php

declare(strict_types=1);

namespace App\DTOs\TrainingSkill;

/**
 * DTO for creating a new training skill/type
 */
class CreateTrainingSkillDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly string $name,
        public readonly string $type = 'training_type',
    ) {}

    /**
     * Create DTO from validated request data
     */
    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            name: $data['name'],
        );
    }

    /**
     * Convert to array for model creation
     */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'type' => $this->type,
            'category_name' => $this->name,
        ];
    }
}
