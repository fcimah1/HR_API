<?php

namespace App\Repository\Interface;

use App\Models\ResidenceRenewalCost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ResidenceRenewalRepositoryInterface
{

    public function getRenewals(int $companyId, array $filters, int $perPage): LengthAwarePaginator;
    public function create(array $data): ResidenceRenewalCost;
    public function delete(ResidenceRenewalCost $renewal): bool;
    public function find(int $id, int $companyId): ?ResidenceRenewalCost;
}
