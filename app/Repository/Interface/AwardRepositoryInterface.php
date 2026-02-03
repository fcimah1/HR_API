<?php

namespace App\Repository\Interface;

use App\Models\Award;
use Illuminate\Pagination\LengthAwarePaginator;

interface AwardRepositoryInterface
{
    public function getAwards(int $companyId, array $filters, int $perPage): LengthAwarePaginator;
    public function create(array $data): Award;
    public function update(Award $award, array $data): Award;
    public function delete(Award $award): bool;
    public function find(int $id, int $companyId): ?Award;
}
