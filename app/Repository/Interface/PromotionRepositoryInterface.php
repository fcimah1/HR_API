<?php

namespace App\Repository\Interface;

use App\Models\Promotion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PromotionRepositoryInterface
{
    public function getPromotions(int $companyId, array $filters, int $perPage): LengthAwarePaginator;
    public function create(array $data): Promotion;
    public function update(Promotion $promotion, array $data): Promotion;
    public function delete(Promotion $promotion): bool;
    public function find(int $id, int $companyId): ?Promotion;
}
