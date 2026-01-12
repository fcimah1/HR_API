<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Trainer\CreateTrainerDTO;
use App\DTOs\Trainer\TrainerFilterDTO;
use App\Models\Trainer;
use App\Repository\Interface\TrainerRepositoryInterface;
use Illuminate\Support\Facades\Log;

class TrainerService
{
    public function __construct(
        protected TrainerRepositoryInterface $trainerRepository,
    ) {}

    /**
     * Get paginated trainers with filters
     */
    public function getPaginatedTrainers(TrainerFilterDTO $filters): array
    {
        return $this->trainerRepository->getPaginatedTrainers($filters);
    }

    /**
     * Get all trainers for company (for dropdowns)
     */
    public function getAllForCompany(int $companyId): array
    {
        return $this->trainerRepository->getAllForCompany($companyId);
    }

    /**
     * Create a new trainer
     */
    public function createTrainer(CreateTrainerDTO $dto): array
    {
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
     * Update trainer
     */
    public function updateTrainer(int $id, array $data, int $companyId): ?array
    {
        $trainer = $this->trainerRepository->findByIdInCompany($id, $companyId);

        if (!$trainer) {
            return null;
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
     * Delete trainer
     */
    public function deleteTrainer(int $id, int $companyId): bool
    {
        $trainer = $this->trainerRepository->findByIdInCompany($id, $companyId);

        if (!$trainer) {
            return false;
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
