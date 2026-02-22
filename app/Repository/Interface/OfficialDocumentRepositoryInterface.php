<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\DTOs\Document\OfficialDocumentFilterDTO;
use App\Models\OfficialDocument;
use Illuminate\Pagination\LengthAwarePaginator;

interface OfficialDocumentRepositoryInterface
{
    public function getPaginatedDocuments(OfficialDocumentFilterDTO $filters): array;

    public function create(array $data): OfficialDocument;

    public function update(int $id, array $data, int $companyId): ?OfficialDocument;

    public function delete(int $id, int $companyId): bool;

    public function findById(int $id, int $companyId): ?OfficialDocument;
}
