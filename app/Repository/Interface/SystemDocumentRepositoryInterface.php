<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\Models\SystemDocument;
use App\DTOs\Document\SystemDocumentFilterDTO;
use Illuminate\Pagination\LengthAwarePaginator;

interface SystemDocumentRepositoryInterface
{
    public function getPaginatedDocuments(SystemDocumentFilterDTO $filters): array;

    public function createDocument(array $data): SystemDocument;

    public function updateDocument(int $id, array $data): ?SystemDocument;

    public function deleteDocument(int $id): bool;

    public function getDocumentById(int $id, int $companyId): ?SystemDocument;
}
