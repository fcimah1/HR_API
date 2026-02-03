<?php

namespace App\Services;

use App\Repository\Interface\AwardConfigurationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class AwardConfigurationService
{
    protected $repository;

    public function __construct(AwardConfigurationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getTypes(int $companyId, ?string $search = null): Collection
    {
        return $this->repository->getTypes($companyId, $search);
    }

    public function createType(int $companyId, string $name)
    {
        return $this->repository->create([
            'company_id' => $companyId,
            'category_name' => $name,
        ]);
    }

    public function updateType(int $companyId, int $id, string $name)
    {
        return $this->repository->update($id, $companyId, [
            'category_name' => $name,
        ]);
    }

    public function deleteType(int $companyId, int $id): bool
    {
        return $this->repository->delete($id, $companyId);
    }
}
