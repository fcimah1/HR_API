<?php

namespace App\Repository\Interface\Recruitment;

use App\Models\Interview;
use Illuminate\Pagination\LengthAwarePaginator;

interface InterviewRepositoryInterface
{
    public function getAll(array $filters = []): mixed;
    public function getById(int $id, int $companyId): ?Interview;
    public function update(\App\Models\Interview $interview, array $data): bool;
}
