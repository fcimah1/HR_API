<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\DTOs\Training\CreateTrainingDTO;
use App\DTOs\Training\TrainingFilterDTO;
use App\DTOs\Training\UpdateTrainingDTO;
use App\Models\Training;
use App\Models\User;

interface TrainingRepositoryInterface
{
    /**
     * Get paginated trainings with filters
     */
    public function getPaginatedTrainings(TrainingFilterDTO $filters, User $user): array;

    /**
     * Find training by ID
     */
    public function findById(int $id): ?Training;

    /**
     * Find training by ID for specific company
     */
    public function findByIdInCompany(int $id, int $companyId): ?Training;

    /**
     * Create a new training
     */
    public function create(CreateTrainingDTO $dto): Training;

    /**
     * Update training
     */
    public function update(Training $training, UpdateTrainingDTO $dto): Training;

    /**
     * Delete training
     */
    public function delete(Training $training): bool;

    /**
     * Update training status
     */
    public function updateStatus(Training $training, int $status, ?int $performance = null, ?string $remarks = null): Training;

    /**
     * Add note to training
     */
    public function addNote(int $trainingId, int $companyId, int $employeeId, string $note): object;

    /**
     * Get training notes
     */
    public function getNotes(int $trainingId): array;

    /**
     * Get training statistics for company
     */
    public function getStatistics(int $companyId): array;
}
