<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Trainer\CreateTrainerDTO;
use App\DTOs\Trainer\TrainerFilterDTO;
use App\Models\Trainer;
use App\Models\User;
use App\Repository\Interface\TrainerRepositoryInterface;
use Illuminate\Support\Facades\Log;

class TrainerService
{
    public function __construct(
        protected TrainerRepositoryInterface $trainerRepository,
        protected SimplePermissionService $permissionService,
    ) {}

    /**
     * Get paginated trainers with filters
     */
    public function getPaginatedTrainers(TrainerFilterDTO $filters, ?User $user = null): array
    {
        return $this->trainerRepository->getPaginatedTrainers($filters);
    }

    /**
     * Get all trainers for company (for dropdowns) with operation restrictions
     */
    public function getAllForCompany(int $companyId, ?User $user = null): array
    {
        $trainers = $this->trainerRepository->getAllForCompany($companyId);

        // Apply operation restrictions if user provided
        if ($user && !$this->permissionService->isCompanyOwner($user)) {
            $restrictedTypes = $this->permissionService->getRestrictedValues(
                $user->user_id,
                $companyId,
                'trainer_'
            );

            if (!empty($restrictedTypes)) {
                $trainers = array_filter(
                    $trainers,
                    fn($trainer) => !in_array($trainer['trainer_id'], $restrictedTypes)
                );
            }
        }

        return $trainers;
    }

    /**
     * Create a new trainer with permission checks
     */
    public function createTrainer(CreateTrainerDTO $dto, ?User $user = null): array
    {
        // Check operation restriction for creating trainers
        if ($user && !$this->permissionService->isCompanyOwner($user)) {
            $restrictedTypes = $this->permissionService->getRestrictedValues(
                $user->user_id,
                $dto->companyId,
                'trainer_'
            );

            if (!empty($restrictedTypes) && in_array(0, $restrictedTypes)) {
                throw new \Exception('ليس لديك صلاحية لإنشاء مدرب');
            }
        }

        $trainer = $this->trainerRepository->create($dto);

        Log::info('Trainer created', [
            'trainer_id' => $trainer->trainer_id,
            'company_id' => $dto->companyId,
        ]);

        return $this->transformTrainerResponse($trainer);
    }

    /**
     * Get trainer by ID
     */
    public function getTrainerById(int $id, int $companyId): ?array
    {
        $trainer = $this->trainerRepository->findByIdInCompany($id, $companyId);

        if (!$trainer) {
            return null;
        }

        return $this->transformTrainerResponse($trainer);
    }

    /**
     * Update trainer with permission checks
     */
    public function updateTrainer(int $id, array $data, int $companyId, ?User $user = null): ?array
    {
        $trainer = $this->trainerRepository->findByIdInCompany($id, $companyId);

        if (!$trainer) {
            return null;
        }

        // Check operation restriction for updating trainers
        if ($user && !$this->permissionService->isCompanyOwner($user)) {
            $restrictedTypes = $this->permissionService->getRestrictedValues(
                $user->user_id,
                $companyId,
                'trainer_'
            );

            if (!empty($restrictedTypes) && in_array($id, $restrictedTypes)) {
                throw new \Exception('ليس لديك صلاحية لتعديل هذا المدرب');
            }
        }

        // Filter only updateable fields
        $updateData = array_filter([
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'contact_number' => $data['contact_number'] ?? null,
            'email' => $data['email'] ?? null,
            'expertise' => $data['expertise'] ?? null,
            'address' => $data['address'] ?? null,
        ], fn($v) => $v !== null);

        if (empty($updateData)) {
            return $this->transformTrainerResponse($trainer);
        }

        $trainer = $this->trainerRepository->update($trainer, $updateData);

        Log::info('Trainer updated', [
            'trainer_id' => $trainer->trainer_id,
            'company_id' => $companyId,
        ]);

        return $this->transformTrainerResponse($trainer);
    }

    /**
     * Delete trainer with permission checks
     */
    public function deleteTrainer(int $id, int $companyId, ?User $user = null): bool
    {
        $trainer = $this->trainerRepository->findByIdInCompany($id, $companyId);

        if (!$trainer) {
            return false;
        }

        // Check operation restriction for deleting trainers
        if ($user && !$this->permissionService->isCompanyOwner($user)) {
            $restrictedTypes = $this->permissionService->getRestrictedValues(
                $user->user_id,
                $companyId,
                'trainer_'
            );

            if (!empty($restrictedTypes) && in_array($id, $restrictedTypes)) {
                throw new \Exception('ليس لديك صلاحية لحذف هذا المدرب');
            }
        }

        // Check if trainer has any trainings
        if ($trainer->trainings()->count() > 0) {
            throw new \Exception('Cannot delete trainer with associated trainings');
        }

        $result = $this->trainerRepository->delete($trainer);

        if ($result) {
            Log::info('Trainer deleted', [
                'trainer_id' => $id,
                'company_id' => $companyId,
            ]);
        }

        return $result;
    }

    /**
     * Transform trainer for API response
     */
    private function transformTrainerResponse(Trainer $trainer): array
    {
        return [
            'trainer_id' => $trainer->trainer_id,
            'company_id' => $trainer->company_id,
            'first_name' => $trainer->first_name,
            'last_name' => $trainer->last_name,
            'full_name' => $trainer->full_name,
            'contact_number' => $trainer->contact_number,
            'email' => $trainer->email,
            'expertise' => $trainer->expertise,
            'address' => $trainer->address,
            'trainings_count' => $trainer->trainings_count,
            'created_at' => $trainer->created_at,
        ];
    }
}
