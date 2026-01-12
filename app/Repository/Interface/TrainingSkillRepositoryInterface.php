<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\DTOs\TrainingSkill\CreateTrainingSkillDTO;
use App\DTOs\TrainingSkill\UpdateTrainingSkillDTO;
use Illuminate\Support\Collection;

interface TrainingSkillRepositoryInterface
{
    /**
     * Get all training skills for company (including global)
     */
    public function getAllForCompany(int $companyId): Collection;

    /**
     * Find training skill by ID for specific company
     */
    public function findByIdInCompany(int $id, int $companyId): ?object;

    /**
     * Create a new training skill
     */
    public function create(CreateTrainingSkillDTO $dto): object;

    /**
     * Update training skill
     */
    public function update(object $skill, UpdateTrainingSkillDTO $dto): object;

    /**
     * Delete training skill
     */
    public function delete(object $skill): bool;

    /**
     * Check if skill is used in trainings
     */
    public function isInUse(int $id): int;
}
