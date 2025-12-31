<?php

namespace App\Repository;

use App\DTOs\TravelType\CreateTravelTypeDTO;
use App\DTOs\TravelType\UpdateTravelTypeDTO;
use App\Models\ErpConstant;
use App\Repository\Interface\TravelTypeRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class TravelTypeRepository implements TravelTypeRepositoryInterface
{
    public function create(CreateTravelTypeDTO $data): ErpConstant
    {
        return ErpConstant::create($data->toArray());
    }

    public function update(ErpConstant $travelType, UpdateTravelTypeDTO $data): ErpConstant
    {
        $travelType->update($data->toArray());
        return $travelType->fresh();
    }

    public function delete(int $id): bool
    {
        return ErpConstant::destroy($id) > 0;
    }

    public function findById(int $id): ?ErpConstant
    {
        return ErpConstant::where('constants_id', $id)
            ->where('type', 'travel_type')
            ->first();
    }

    public function findByIdAndCompany(int $id, int $companyId): ?ErpConstant
    {
        return ErpConstant::where('constants_id', $id)
            ->where('company_id', $companyId)
            ->where('type', 'travel_type')
            ->first();
    }

    public function getByCompany(int $companyId, int $perPage = 15, array $excludedIds = []): LengthAwarePaginator
    {
        return ErpConstant::where('company_id', $companyId)
            ->where('type', 'travel_type')
            ->when(!empty($excludedIds), function ($q) use ($excludedIds) {
                $q->whereNotIn('constants_id', $excludedIds);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function search(int $companyId, string $query, int $perPage = 15, array $excludedIds = []): LengthAwarePaginator
    {
        return ErpConstant::where('company_id', $companyId)
            ->where('type', 'travel_type')
            ->where('category_name', 'like', "%{$query}%")
            ->when(!empty($excludedIds), function ($q) use ($excludedIds) {
                $q->whereNotIn('constants_id', $excludedIds);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
