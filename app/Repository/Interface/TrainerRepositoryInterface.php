<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\DTOs\Trainer\CreateTrainerDTO;
use App\DTOs\Trainer\TrainerFilterDTO;
use App\Models\Trainer;

interface TrainerRepositoryInterface
{
    /**
     * Get paginated trainers with filters
     */
    public function getPaginatedTrainers(TrainerFilterDTO $filters): array;

    /**
     * Get all trainers for company (for dropdowns)
     */
    public function getAllForCompany(int $companyId): array;

    /**
     * Find trainer by ID
     */
    public function findById(int $id): ?Trainer;

    /**
     * Find trainer by ID for specific company
     */
    public function findByIdInCompany(int $id, int $companyId): ?Trainer;

    /**
     * Create a new trainer
     */
    public function create(CreateTrainerDTO $dto): Trainer;

    /**
     * Update trainer
     */
    public function update(Trainer $trainer, array $data): Trainer;

    /**
     * Delete trainer
     */
    public function delete(Trainer $trainer): bool;
}
