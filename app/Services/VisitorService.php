<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\Interface\VisitorRepositoryInterface;
use App\DTOs\Visitor\CreateVisitorDTO;
use App\DTOs\Visitor\UpdateVisitorDTO;
use App\DTOs\Visitor\VisitorFilterDTO;
use App\Models\Visitor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Exception;

class VisitorService
{
    public function __construct(
        private readonly VisitorRepositoryInterface $visitorRepository
    ) {}

    public function getVisitors(VisitorFilterDTO $filters, int $companyId): Collection|LengthAwarePaginator
    {
        return $this->visitorRepository->getAll($filters, $companyId);
    }

    public function getVisitor(int $id, int $companyId): Visitor
    {
        $visitor = $this->visitorRepository->findById($id, $companyId);
        if (!$visitor) {
            throw new Exception('سجل الزائر غير موجود.');
        }
        return $visitor;
    }

    public function createVisitor(CreateVisitorDTO $dto): Visitor
    {
        return $this->visitorRepository->create($dto->toArray());
    }

    public function updateVisitor(int $id, int $companyId, UpdateVisitorDTO $dto): Visitor
    {
        $visitor = $this->getVisitor($id, $companyId);
        $this->visitorRepository->update($visitor, $dto->toArray());
        return $visitor->fresh();
    }

    public function deleteVisitor(int $id, int $companyId): bool
    {
        $visitor = $this->getVisitor($id, $companyId);
        return $this->visitorRepository->delete($visitor);
    }
}
