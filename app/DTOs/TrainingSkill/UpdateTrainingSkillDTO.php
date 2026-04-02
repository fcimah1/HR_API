<?php

declare(strict_types=1);

namespace App\DTOs\TrainingSkill;

/**
 * DTO for updating a training skill/type
 */
class UpdateTrainingSkillDTO
{
    public function __construct(
        public readonly string $name,
    ) {}

    /**
     * Create DTO from validated request data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
        );
    }

    /**
     * Convert to array for model update
     */
    public function toArray(): array
    {
        return [
            'category_name' => $this->name,
        ];
    }
}
