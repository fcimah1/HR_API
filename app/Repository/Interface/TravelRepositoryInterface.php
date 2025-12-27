<?php

namespace App\Repository\Interface;

use App\DTOs\Travel\CreateTravelDTO;
use App\DTOs\Travel\TravelRequestFilterDTO;
use App\DTOs\Travel\UpdateTravelDTO;
use App\Models\Travel;

interface TravelRepositoryInterface
{
    public function create(CreateTravelDTO $data): Travel;
    public function update(Travel $travel, UpdateTravelDTO $data): Travel;
    public function cancel(int $id): bool;
    public function findById(int $id): ?Travel;
    public function findByIdAndCompany(int $id, int $companyId): ?Travel;
    public function getByCompany(int $companyId, TravelRequestFilterDTO $filters): array;
    public function getByEmployee(int $employeeId, TravelRequestFilterDTO $filters): array;
    public function approve(int $id, int $approvedBy): Travel;
    public function reject(int $id, int $rejectedBy): Travel;
    public function hasOverlappingTravel(int $employeeId, string $startDate, string $endDate, ?int $excludeTravelId = null): bool;
}
