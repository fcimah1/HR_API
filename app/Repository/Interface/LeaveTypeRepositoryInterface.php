<?php

namespace App\Repository\Interface;

use App\DTOs\Leave\CreateLeaveTypeDTO;
use App\DTOs\Leave\UpdateLeaveTypeDTO;
use Illuminate\Support\Collection;

interface LeaveTypeRepositoryInterface
{
    public function getActiveLeaveTypes(int $companyId): Collection;
    public function findById(int $id): ?object;
    public function create(CreateLeaveTypeDTO $dto): object;
    public function update(UpdateLeaveTypeDTO $dto): object;
    public function delete(int $id, int $companyId): bool;
}
