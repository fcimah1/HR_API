<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\TaxTypeRepository;
use App\Models\ErpConstant;
use App\DTOs\Inventory\TaxTypeDTO;
use App\DTOs\Inventory\TaxTypeFilterDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class TaxTypeService
{
    public function __construct(
        private readonly TaxTypeRepository $taxTypeRepository
    ) {}

    public function getTaxTypes(TaxTypeFilterDTO $filters): LengthAwarePaginator|Collection
    {
        Log::info('TaxTypeService::getTaxTypes called', ['filters' => $filters]);
        return $this->taxTypeRepository->getAll($filters);
    }

    public function getTaxTypeById(int $id, int $companyId): ?ErpConstant
    {
        Log::info('TaxTypeService::getTaxTypeById called', ['id' => $id, 'company_id' => $companyId]);
        return $this->taxTypeRepository->findById($id, $companyId);
    }

    public function createTaxType(TaxTypeDTO $dto): ErpConstant
    {
        Log::info('TaxTypeService::createTaxType called', ['tax_name' => $dto->tax_name]);
        return $this->taxTypeRepository->create([
            'company_id' => $dto->company_id,
            'type' => 'tax_type',
            'category_name' => $dto->tax_name,
            'field_one' => $dto->tax_rate,
            'field_two' => $dto->tax_type,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function updateTaxType(int $id, int $companyId, TaxTypeDTO $dto): ?ErpConstant
    {
        Log::info('TaxTypeService::updateTaxType called', ['id' => $id]);
        $tax = $this->taxTypeRepository->findById($id, $companyId);
        if (!$tax) {
            return null;
        }

        $this->taxTypeRepository->update($tax, [
            'category_name' => $dto->tax_name,
            'field_one' => $dto->tax_rate,
            'field_two' => $dto->tax_type,
        ]);

        return $tax->fresh();
    }

    public function deleteTaxType(int $id, int $companyId): bool
    {
        Log::info('TaxTypeService::deleteTaxType called', ['id' => $id]);
        $tax = $this->taxTypeRepository->findById($id, $companyId);
        if (!$tax) {
            return false;
        }

        return $this->taxTypeRepository->delete($tax);
    }
}
