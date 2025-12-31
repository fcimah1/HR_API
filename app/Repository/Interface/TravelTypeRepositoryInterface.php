<?php

namespace App\Repository\Interface;

use App\DTOs\TravelType\CreateTravelTypeDTO;
use App\DTOs\TravelType\UpdateTravelTypeDTO;
use App\Models\ErpConstant;
use Illuminate\Pagination\LengthAwarePaginator;

interface TravelTypeRepositoryInterface
{
    public function create(CreateTravelTypeDTO $data): ErpConstant;
    public function update(ErpConstant $travelType, UpdateTravelTypeDTO $data): ErpConstant;
    public function delete(int $id): bool;
    public function findById(int $id): ?ErpConstant;
    public function findByIdAndCompany(int $id, int $companyId): ?ErpConstant;
    public function getByCompany(int $companyId, int $perPage = 15, array $excludedIds = []): LengthAwarePaginator;
    public function search(int $companyId, string $query, int $perPage = 15, array $excludedIds = []): LengthAwarePaginator;
}
