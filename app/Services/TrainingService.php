<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Training\CreateTrainingDTO;
use App\DTOs\Training\TrainingFilterDTO;
use App\DTOs\Training\UpdateTrainingDTO;
use App\Enums\TrainingPerformanceEnum;
use App\Enums\TrainingStatusEnum;
use App\Http\Resources\TrainingResource;
use App\Http\Resources\TrainingNoteResource;
use App\Models\Training;
use App\Models\User;
use App\Repository\Interface\TrainingRepositoryInterface;
use Illuminate\Support\Facades\Log;

class TrainingService
{
    public function __construct(
        protected TrainingRepositoryInterface $trainingRepository,
        protected SimplePermissionService $permissionService,
    ) {}

    /**
     * Get paginated trainings with filters
     */
    public function getPaginatedTrainings(TrainingFilterDTO $filters, User $user): array
    {
        $result = $this->trainingRepository->getPaginatedTrainings($filters, $user);

        // Convert data items to Resources
        $result['data'] = TrainingResource::collection(
            collect($result['data'])->map(
                fn($item) => is_array($item)
                    ? Training::find($item['training_id'])
                    : $item
            )->filter()
        )->resolve();

        return $result;
    }

    /**
     * Get training enums for dropdowns
     */
    public function getTrainingEnums(): array
    {
        return [
            'statuses' => TrainingStatusEnum::toArray(),
            'performance_levels' => TrainingPerformanceEnum::toArray(),
        ];
    }

    /**
     * Create a new training
     */
    public function createTraining(CreateTrainingDTO $dto): TrainingResource
    {
        $training = $this->trainingRepository->create($dto);

        Log::info('Training created', [
            'training_id' => $training->training_id,
            'company_id' => $dto->companyId,
        ]);

        return new TrainingResource($training);
    }

    /**
     * Get training by ID with permission check
     */
    public function getTrainingById(int $id, int $companyId): ?TrainingResource
    {
        $training = $this->trainingRepository->findByIdInCompany($id, $companyId);

        if (!$training) {
            return null;
        }

        return new TrainingResource($training);
    }

    /**
     * Update training
     */
    public function updateTraining(int $id, UpdateTrainingDTO $dto, int $companyId): ?TrainingResource
    {
        $training = $this->trainingRepository->findByIdInCompany($id, $companyId);

        if (!$training) {
            return null;
        }

        $training = $this->trainingRepository->update($training, $dto);

        Log::info('Training updated', [
            'training_id' => $training->training_id,
            'company_id' => $companyId,
        ]);

        return new TrainingResource($training);
    }

    /**
     * Delete training
     */
    public function deleteTraining(int $id, int $companyId): bool
    {
        $training = $this->trainingRepository->findByIdInCompany($id, $companyId);

        if (!$training) {
            return false;
        }

        $result = $this->trainingRepository->delete($training);

        if ($result) {
            Log::info('Training deleted', [
                'training_id' => $id,
                'company_id' => $companyId,
            ]);
        }

        return $result;
    }

    /**
     * Update training status and optionally performance
     */
    public function updateTrainingStatus(int $id, int $status, int $companyId, ?int $performance = null, ?string $remarks = null): ?TrainingResource
    {
        $training = $this->trainingRepository->findByIdInCompany($id, $companyId);

        if (!$training) {
            return null;
        }

        // Validate status
        if (TrainingStatusEnum::tryFrom($status) === null) {
            throw new \InvalidArgumentException('Invalid training status');
        }

        // Validate performance if provided
        if ($performance !== null && TrainingPerformanceEnum::tryFrom($performance) === null) {
            throw new \InvalidArgumentException('Invalid performance level');
        }

        $training = $this->trainingRepository->updateStatus($training, $status, $performance, $remarks);

        Log::info('Training status updated', [
            'training_id' => $training->training_id,
            'new_status' => $status,
            'performance' => $performance,
        ]);

        return new TrainingResource($training);
    }

    /**
     * Add note to training
     */
    public function addNote(int $trainingId, int $companyId, int $employeeId, string $note): TrainingNoteResource
    {
        // Verify training exists in company
        $training = $this->trainingRepository->findByIdInCompany($trainingId, $companyId);

        if (!$training) {
            throw new \Exception('Training not found');
        }

        $noteRecord = $this->trainingRepository->addNote($trainingId, $companyId, $employeeId, $note);

        Log::info('Training note added', [
            'training_id' => $trainingId,
            'employee_id' => $employeeId,
        ]);

        return new TrainingNoteResource($noteRecord);
    }

    /**
     * Get training notes
     */
    public function getNotes(int $trainingId, int $companyId): array
    {
        // Verify training exists in company
        $training = $this->trainingRepository->findByIdInCompany($trainingId, $companyId);

        if (!$training) {
            throw new \Exception('Training not found');
        }

        return $this->trainingRepository->getNotes($trainingId);
    }

    /**
     * Get training statistics
     */
    public function getStatistics(int $companyId): array
    {
        return $this->trainingRepository->getStatistics($companyId);
    }
}
