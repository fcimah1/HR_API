<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\Models\Visitor;
use App\DTOs\Visitor\VisitorFilterDTO;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface VisitorRepositoryInterface
{
    public function findById(int $id, int $companyId): ?Visitor;
    public function getAll(VisitorFilterDTO $filters, int $companyId): Collection|LengthAwarePaginator;
    public function create(array $data): Visitor;
    public function update(Visitor $visitor, array $data): bool;
    public function delete(Visitor $visitor): bool;
}
