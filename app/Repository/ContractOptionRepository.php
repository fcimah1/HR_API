<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTOs\ContractOption\StoreContractOptionDTO;
use App\DTOs\ContractOption\UpdateContractOptionDTO;
use App\Models\ContractOption;
use App\Repository\Interface\ContractOptionRepositoryInterface;
use Illuminate\Support\Collection;

class ContractOptionRepository implements ContractOptionRepositoryInterface
{
    public function getAllForCompanyByType(int $companyId, string $type, array $filters = []): Collection
    {
        $query = ContractOption::query()
            ->where('company_id', $companyId)
            ->where('salay_type', $type)
            ->orderBy('contract_option_id', 'desc');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('option_title', 'like', "%{$search}%");
        }

        return $query->get();
    }

    public function getByIdForCompanyByType(int $companyId, int $id, string $type): ?ContractOption
    {
        return ContractOption::query()
            ->where('contract_option_id', $id)
            ->where('company_id', $companyId)
            ->where('salay_type', $type)
            ->first();
    }

    public function create(StoreContractOptionDTO $dto): ContractOption
    {
        return ContractOption::create($dto->toArray());
    }

    public function update(ContractOption $option, UpdateContractOptionDTO $dto): ContractOption
    {
        $option->update($dto->toArray());
        return $option->refresh();
    }

    public function deleteForCompanyByType(int $companyId, int $id, string $type): bool
    {
        $option = $this->getByIdForCompanyByType($companyId, $id, $type);
        if (!$option) {
            return false;
        }

        return (bool) $option->delete();
    }
}
