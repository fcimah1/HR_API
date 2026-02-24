<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\TrainingSkill\CreateTrainingSkillDTO;
use App\DTOs\TrainingSkill\UpdateTrainingSkillDTO;
use App\Models\User;
use App\Repository\Interface\TrainingSkillRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TrainingSkillService
{
    public function __construct(
        protected TrainingSkillRepositoryInterface $trainingSkillRepository,
        protected SimplePermissionService $permissionService,
    ) {}

    /**
     * Get all training skills for company with operation restrictions
     */
    public function getAllForCompany(int $companyId, ?User $user = null): Collection
    {
        $skills = $this->trainingSkillRepository->getAllForCompany($companyId);

        // Apply operation restrictions if user provided
        if ($user && !$this->permissionService->isCompanyOwner($user)) {
            $restrictedTypes = $this->permissionService->getRestrictedValues(
                $user->user_id,
                $companyId,
                'training_type_'
            );

            if (!empty($restrictedTypes)) {
                $skills = $skills->filter(fn($skill) => !in_array($skill->id, $restrictedTypes))->values();
            }
        }

        return $skills;
    }

    /**
     * Create a new training skill with permission checks
     */
    public function createTrainingSkill(CreateTrainingSkillDTO $dto, ?User $user = null): array
    {
        // Check operation restriction for creating training skills
        if ($user && !$this->permissionService->isCompanyOwner($user)) {
            $restrictedTypes = $this->permissionService->getRestrictedValues(
                $user->user_id,
                $dto->companyId,
                'training_type_'
            );

            if (!empty($restrictedTypes) && in_array(0, $restrictedTypes)) {
                throw new \Exception('ليس لديك صلاحية لإنشاء نوع تدريب جديد');
            }
        }

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
     * Update training skill with permission checks
     */
    public function updateTrainingSkill(int $id, UpdateTrainingSkillDTO $dto, int $companyId, ?User $user = null): ?array
    {
        $skill = $this->trainingSkillRepository->findByIdInCompany($id, $companyId);

        if (!$skill) {
            return null;
        }

        // Check operation restriction for updating training skills
        if ($user && !$this->permissionService->isCompanyOwner($user)) {
            $restrictedTypes = $this->permissionService->getRestrictedValues(
                $user->user_id,
                $companyId,
                'training_type_'
            );

            if (!empty($restrictedTypes) && in_array($id, $restrictedTypes)) {
                throw new \Exception('ليس لديك صلاحية لتعديل هذا النوع من التدريب');
            }
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
     * Delete training skill with permission checks
     */
    public function deleteTrainingSkill(int $id, int $companyId, ?User $user = null): bool|string
    {
        $skill = $this->trainingSkillRepository->findByIdInCompany($id, $companyId);

        if (!$skill) {
            return false;
        }

        // Check operation restriction for deleting training skills
        if ($user && !$this->permissionService->isCompanyOwner($user)) {
            $restrictedTypes = $this->permissionService->getRestrictedValues(
                $user->user_id,
                $companyId,
                'training_type_'
            );

            if (!empty($restrictedTypes) && in_array($id, $restrictedTypes)) {
                throw new \Exception('ليس لديك صلاحية لحذف هذا النوع من التدريب');
            }
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
