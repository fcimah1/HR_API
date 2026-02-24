<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ContractOption\StoreContractOptionDTO;
use App\DTOs\ContractOption\UpdateContractOptionDTO;
use App\Models\ContractOption;
use App\Repository\Interface\ContractOptionRepositoryInterface;
use Illuminate\Support\Collection;

class ContractOptionService
{
    public function __construct(
        private readonly ContractOptionRepositoryInterface $contractOptionRepository,
    ) {}

    public function getAllForCompanyByType(int $companyId, string $type, array $filters = []): Collection
    {
        return $this->contractOptionRepository->getAllForCompanyByType($companyId, $type, $filters);
    }

    public function getByIdForCompanyByType(int $companyId, int $id, string $type): ?ContractOption
    {
        return $this->contractOptionRepository->getByIdForCompanyByType($companyId, $id, $type);
    }

    public function store(StoreContractOptionDTO $dto): ContractOption
    {
        return $this->contractOptionRepository->create($dto);
    }

    public function updateForCompanyByType(int $companyId, int $id, UpdateContractOptionDTO $dto): ?ContractOption
    {
        $option = $this->contractOptionRepository->getByIdForCompanyByType($companyId, $id, $dto->type);
        if (!$option) {
            return null;
        }

        return $this->contractOptionRepository->update($option, $dto);
    }

    public function deleteForCompanyByType(int $companyId, int $id, string $type): bool
    {
        return $this->contractOptionRepository->deleteForCompanyByType($companyId, $id, $type);
    }
}
