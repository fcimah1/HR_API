<?php

namespace App\Repository\Interface;

use App\Models\Termination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TerminationRepositoryInterface
{
    public function getTerminations(int $companyId, array $filters, int $perPage): LengthAwarePaginator;
    public function create(array $data): Termination;
    public function update(Termination $termination, array $data): Termination;
    public function delete(Termination $termination): bool;
    public function find(int $id, int $companyId): ?Termination;
}
