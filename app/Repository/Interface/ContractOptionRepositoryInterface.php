<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\DTOs\ContractOption\StoreContractOptionDTO;
use App\DTOs\ContractOption\UpdateContractOptionDTO;
use App\Models\ContractOption;
use Illuminate\Support\Collection;

interface ContractOptionRepositoryInterface
{
    public function getAllForCompanyByType(int $companyId, string $type, array $filters = []): Collection;
    public function getByIdForCompanyByType(int $companyId, int $id, string $type): ?ContractOption;
    public function create(StoreContractOptionDTO $dto): ContractOption;
    public function update(ContractOption $option, UpdateContractOptionDTO $dto): ContractOption;
    public function deleteForCompanyByType(int $companyId, int $id, string $type): bool;
}
