<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\TrainingSkill\CreateTrainingSkillDTO;
use App\DTOs\TrainingSkill\UpdateTrainingSkillDTO;
use App\Repository\Interface\TrainingSkillRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TrainingSkillService
{
    public function __construct(
        protected TrainingSkillRepositoryInterface $trainingSkillRepository,
    ) {}

    /**
     * Get all training skills for company
     */
    public function getAllForCompany(int $companyId): Collection
    {
        return $this->trainingSkillRepository->getAllForCompany($companyId);
    }

    /**
     * Create a new training skill
     */
    public function createTrainingSkill(CreateTrainingSkillDTO $dto): array
    {
        $skill = $this->trainingSkillRepository->create($dto);

        Log::info('Training skill created', [
            'skill_id' => $skill->constants_id,
            'company_id' => $dto->companyId,
        ]);

        return [
            'id' => $skill->constants_id,
            'name' => $skill->category_name,
            'company_id' => $skill->company_id,
        ];
    }

    /**
     * Update training skill
     */
    public function updateTrainingSkill(int $id, UpdateTrainingSkillDTO $dto, int $companyId): ?array
    {
        $skill = $this->trainingSkillRepository->findByIdInCompany($id, $companyId);

        if (!$skill) {
            return null;
        }

        $skill = $this->trainingSkillRepository->update($skill, $dto);

        Log::info('Training skill updated', [
            'skill_id' => $id,
            'company_id' => $companyId,
        ]);

        return [
            'id' => $skill->constants_id,
            'name' => $skill->category_name,
            'company_id' => $skill->company_id,
        ];
    }

    /**
     * Delete training skill
     */
    public function deleteTrainingSkill(int $id, int $companyId): bool|string
    {
        $skill = $this->trainingSkillRepository->findByIdInCompany($id, $companyId);

        if (!$skill) {
            return false;
        }

        // Check if skill is in use
        $usedCount = $this->trainingSkillRepository->isInUse($id);
        if ($usedCount > 0) {
            return 'لا يمكن حذف مهارة التدريب لأنها مستخدمة في ' . $usedCount . ' تدريب';
        }

        $result = $this->trainingSkillRepository->delete($skill);

        if ($result) {
            Log::info('Training skill deleted', [
                'skill_id' => $id,
                'company_id' => $companyId,
            ]);
        }

        return $result;
    }
}
