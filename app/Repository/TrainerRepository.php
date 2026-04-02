<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTOs\Trainer\CreateTrainerDTO;
use App\DTOs\Trainer\TrainerFilterDTO;
use App\Models\Trainer;
use App\Repository\Interface\TrainerRepositoryInterface;

class TrainerRepository implements TrainerRepositoryInterface
{
    /**
     * Get paginated trainers with filters
     */
    public function getPaginatedTrainers(TrainerFilterDTO $filters): array
    {
        $query = Trainer::where('company_id', $filters->companyId);

        // Search filter (name)
        if ($filters->search !== null && trim($filters->search) !== '') {
            $searchTerm = '%' . $filters->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', $searchTerm)
                    ->orWhere('last_name', 'like', $searchTerm)
                    ->orWhere('expertise', 'like', $searchTerm);
            });
        }

        // Email filter
        if ($filters->email !== null && trim($filters->email) !== '') {
            $query->where('email', 'like', '%' . $filters->email . '%');
        }

        // Sorting
        $sortBy = in_array($filters->sortBy, ['created_at', 'first_name', 'last_name', 'email'])
            ? $filters->sortBy
            : 'created_at';
        $sortDirection = strtolower($filters->sortDirection) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $paginator = $query->paginate($filters->perPage, ['*'], 'page', $filters->page);

        return [
            'data' => collect($paginator->items())->map(fn($t) => $this->transformTrainer($t))->toArray(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /**
     * Get all trainers for company (for dropdowns)
     */
    public function getAllForCompany(int $companyId): array
    {
        return Trainer::where('company_id', $companyId)
            ->orderBy('first_name')
            ->get()
            ->map(fn($t) => [
                'trainer_id' => $t->trainer_id,
                'full_name' => $t->full_name,
                'email' => $t->email,
            ])
            ->toArray();
    }

    /**
     * Find trainer by ID
     */
    public function findById(int $id): ?Trainer
    {
        return Trainer::find($id);
    }

    /**
     * Find trainer by ID for specific company
     */
    public function findByIdInCompany(int $id, int $companyId): ?Trainer
    {
        return Trainer::where('company_id', $companyId)->find($id);
    }

    /**
     * Create a new trainer
     */
    public function create(CreateTrainerDTO $dto): Trainer
    {
        return Trainer::create($dto->toArray());
    }

    /**
     * Update trainer
     */
    public function update(Trainer $trainer, array $data): Trainer
    {
        $trainer->update($data);
        $trainer->refresh();
        return $trainer;
    }

    /**
     * Delete trainer
     */
    public function delete(Trainer $trainer): bool
    {
        return $trainer->delete();
    }

    /**
     * Transform trainer for API response
     */
    private function transformTrainer(Trainer $trainer): array
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
