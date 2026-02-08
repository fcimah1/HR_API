<?php

namespace App\Services;

use App\Repository\Interface\DesignationRepositoryInterface;
use App\Models\Designation;
use Illuminate\Support\Collection;

class DesignationService
{
    public function __construct(
        protected DesignationRepositoryInterface $designationRepository
    ) {}

    public function getDesignations(int $companyId, array $filters = []): mixed
    {
        return $this->designationRepository->getAllDesignations($companyId, $filters);
    }

    public function getDesignation(int $id, int $companyId): ?Designation
    {
        return $this->designationRepository->getDesignationById($id, $companyId);
    }

    public function createDesignation(array $data): Designation
    {
        return $this->designationRepository->create($data);
    }

    public function updateDesignation(int $id, int $companyId, array $data): ?Designation
    {
        $designation = $this->getDesignation($id, $companyId);
        if (!$designation) {
            return null;
        }
        return $this->designationRepository->update($designation, $data);
    }

    public function deleteDesignation(int $id, int $companyId): bool
    {
        return $this->designationRepository->delete($id, $companyId);
    }
}
