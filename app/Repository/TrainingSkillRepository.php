<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTOs\TrainingSkill\CreateTrainingSkillDTO;
use App\DTOs\TrainingSkill\UpdateTrainingSkillDTO;
use App\Models\ErpConstant;
use App\Models\Training;
use App\Repository\Interface\TrainingSkillRepositoryInterface;
use Illuminate\Support\Collection;

class TrainingSkillRepository implements TrainingSkillRepositoryInterface
{
    private const CONSTANT_TYPE = 'training_type';

    /**
     * Get all training skills for company (including global)
     */
    public function getAllForCompany(int $companyId): Collection
    {
        return ErpConstant::where('type', self::CONSTANT_TYPE)
            ->whereIn('company_id', [$companyId, 0])
            ->orderBy('company_id', 'desc')
            ->orderBy('category_name')
            ->get()
            ->map(fn($skill) => (object) [
                'id' => $skill->constants_id,
                'name' => $skill->category_name,
                'company_id' => $skill->company_id,
                'is_global' => $skill->company_id === 0,
            ]);
    }

    /**
     * Find training skill by ID for specific company
     */
    public function findByIdInCompany(int $id, int $companyId): ?object
    {
        return ErpConstant::where('constants_id', $id)
            ->where('type', self::CONSTANT_TYPE)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * Create a new training skill
     */
    public function create(CreateTrainingSkillDTO $dto): object
    {
        return ErpConstant::create($dto->toArray());
    }

    /**
     * Update training skill
     */
    public function update(object $skill, UpdateTrainingSkillDTO $dto): object
    {
        $skill->update($dto->toArray());
        $skill->refresh();
        return $skill;
    }

    /**
     * Delete training skill
     */
    public function delete(object $skill): bool
    {
        return $skill->delete();
    }

    /**
     * Check if skill is used in trainings
     */
    public function isInUse(int $id): int
    {
        return Training::where('training_type_id', $id)->count();
    }
}
