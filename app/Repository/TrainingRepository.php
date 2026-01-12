<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTOs\Training\CreateTrainingDTO;
use App\DTOs\Training\TrainingFilterDTO;
use App\DTOs\Training\UpdateTrainingDTO;
use App\Enums\TrainingStatusEnum;
use App\Models\Training;
use App\Models\TrainingNote;
use App\Models\User;
use App\Repository\Interface\TrainingRepositoryInterface;
use Illuminate\Support\Facades\Log;

class TrainingRepository implements TrainingRepositoryInterface
{
    /**
     * Get paginated trainings with filters
     */
    public function getPaginatedTrainings(TrainingFilterDTO $filters, User $user): array
    {
        $query = Training::where('company_id', $filters->companyId)
            ->with(['trainer', 'trainingType', 'department']);

        // Search filter
        if ($filters->search !== null && trim($filters->search) !== '') {
            $searchTerm = '%' . $filters->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->whereHas('trainer', function ($subQ) use ($searchTerm) {
                    $subQ->where('first_name', 'like', $searchTerm)
                        ->orWhere('last_name', 'like', $searchTerm);
                })
                    ->orWhereHas('trainingType', function ($subQ) use ($searchTerm) {
                        $subQ->where('category_name', 'like', $searchTerm);
                    })
                    ->orWhere('description', 'like', $searchTerm)
                    ->orWhere('associated_goals', 'like', $searchTerm);
            });
        }

        // Trainer filter
        if ($filters->trainerId !== null) {
            $query->where('trainer_id', $filters->trainerId);
        }

        // Training type filter
        if ($filters->trainingTypeId !== null) {
            $query->where('training_type_id', $filters->trainingTypeId);
        }

        // Department filter
        if ($filters->departmentId !== null) {
            $query->where('department_id', $filters->departmentId);
        }

        // Status filter
        if ($filters->status !== null) {
            $query->where('training_status', $filters->status);
        }

        // Employee filter (search in comma-separated employee_id)
        if ($filters->employeeId !== null) {
            $query->where(function ($q) use ($filters) {
                $q->where('employee_id', $filters->employeeId)
                    ->orWhere('employee_id', 'like', $filters->employeeId . ',%')
                    ->orWhere('employee_id', 'like', '%,' . $filters->employeeId . ',%')
                    ->orWhere('employee_id', 'like', '%,' . $filters->employeeId);
            });
        }

        // Date range filters
        if ($filters->fromDate !== null) {
            $query->where('start_date', '>=', $filters->fromDate);
        }

        if ($filters->toDate !== null) {
            $query->where('finish_date', '<=', $filters->toDate);
        }

        // Sorting
        $sortBy = in_array($filters->sortBy, ['created_at', 'start_date', 'finish_date', 'training_status', 'training_cost'])
            ? $filters->sortBy
            : 'created_at';
        $sortDirection = strtolower($filters->sortDirection) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $paginator = $query->paginate($filters->perPage, ['*'], 'page', $filters->page);

        return [
            'data' => collect($paginator->items())->map(fn($t) => $this->transformTraining($t))->toArray(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /**
     * Find training by ID
     */
    public function findById(int $id): ?Training
    {
        return Training::with(['trainer', 'trainingType', 'department', 'notes.employee'])->find($id);
    }

    /**
     * Find training by ID for specific company
     */
    public function findByIdInCompany(int $id, int $companyId): ?Training
    {
        return Training::where('company_id', $companyId)
            ->with(['trainer', 'trainingType', 'department', 'notes.employee'])
            ->find($id);
    }

    /**
     * Create a new training
     */
    public function create(CreateTrainingDTO $dto): Training
    {
        $training = Training::create($dto->toArray());
        $training->load(['trainer', 'trainingType', 'department']);
        return $training;
    }

    /**
     * Update training
     */
    public function update(Training $training, UpdateTrainingDTO $dto): Training
    {
        $training->update($dto->toArray());
        $training->refresh();
        $training->load(['trainer', 'trainingType', 'department', 'notes.employee']);
        return $training;
    }

    /**
     * Delete training
     */
    public function delete(Training $training): bool
    {
        // Delete related notes first
        TrainingNote::where('training_id', $training->training_id)->delete();
        return $training->delete();
    }

    /**
     * Update training status and optionally performance
     */
    public function updateStatus(Training $training, int $status, ?int $performance = null, ?string $remarks = null): Training
    {
        $updateData = ['training_status' => $status];

        if ($performance !== null) {
            $updateData['performance'] = $performance;
        }

        if ($remarks !== null) {
            $updateData['remarks'] = $remarks;
        }

        $training->update($updateData);
        $training->refresh();
        $training->load(['trainer', 'trainingType', 'department']);
        return $training;
    }

    /**
     * Add note to training
     */
    public function addNote(int $trainingId, int $companyId, int $employeeId, string $note): object
    {
        return TrainingNote::create([
            'training_id' => $trainingId,
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'training_note' => $note,
            'created_at' => now()->format('d-m-Y H:i:s'),
        ]);
    }

    /**
     * Get training notes
     */
    public function getNotes(int $trainingId): array
    {
        return TrainingNote::where('training_id', $trainingId)
            ->with('employee')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($n) => [
                'id' => $n->training_note_id,
                'note' => $n->training_note,
                'employee_id' => $n->employee_id,
                'employee_name' => $n->employee_name,
                'created_at' => $n->created_at,
            ])
            ->toArray();
    }

    /**
     * Get training statistics for company
     */
    public function getStatistics(int $companyId): array
    {
        return [
            'total' => Training::where('company_id', $companyId)->count(),
            'pending' => Training::where('company_id', $companyId)
                ->where('training_status', TrainingStatusEnum::PENDING->value)->count(),
            'started' => Training::where('company_id', $companyId)
                ->where('training_status', TrainingStatusEnum::STARTED->value)->count(),
            'completed' => Training::where('company_id', $companyId)
                ->where('training_status', TrainingStatusEnum::COMPLETED->value)->count(),
            'rejected' => Training::where('company_id', $companyId)
                ->where('training_status', TrainingStatusEnum::REJECTED->value)->count(),
        ];
    }

    /**
     * Transform training for API response
     */
    private function transformTraining(Training $training): array
    {
        return [
            'training_id' => $training->training_id,
            'company_id' => $training->company_id,
            'department_id' => $training->department_id,
            'department_name' => $training->department_name,
            'employee_ids' => $training->employee_ids_array,
            'training_type_id' => $training->training_type_id,
            'training_type_name' => $training->training_type_name,
            'trainer_id' => $training->trainer_id,
            'trainer_name' => $training->trainer_name,
            'start_date' => $training->start_date,
            'finish_date' => $training->finish_date,
            'training_cost' => $training->training_cost,
            'training_status' => $training->training_status,
            'status_label' => $training->status_label,
            'description' => $training->description,
            'performance' => $training->performance,
            'performance_label' => $training->performance_label,
            'associated_goals' => $training->associated_goals,
            'remarks' => $training->remarks,
            'created_at' => $training->created_at,
        ];
    }
}
