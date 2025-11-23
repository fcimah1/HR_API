<?php

namespace App\Repository\Interface;

use App\DTOs\Travel\CreateTravelDTO;
use App\DTOs\Travel\UpdateTravelDTO;
use App\Models\Travel;
use Illuminate\Pagination\LengthAwarePaginator;

interface TravelRepositoryInterface
{
    public function create(CreateTravelDTO $data): Travel;
    public function update(Travel $travel, UpdateTravelDTO $data): Travel;
    public function cancel(int $id): bool;
    public function findById(int $id): ?Travel;
    public function findByIdAndCompany(int $id, int $companyId): ?Travel;
    public function getByCompany(int $companyId, int $perPage = 15): LengthAwarePaginator;
    public function getByEmployee(int $employeeId, int $perPage = 15): LengthAwarePaginator;
    public function approve(int $id): Travel;
    public function reject(int $id): Travel;
    public function hasOverlappingTravel(int $employeeId, string $startDate, string $endDate, ?int $excludeTravelId = null): bool;
    public function search(int $companyId, string $query, int $perPage = 15): LengthAwarePaginator;
}
