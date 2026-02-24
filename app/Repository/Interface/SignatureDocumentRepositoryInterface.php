<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\Models\SignatureDocument;
use App\DTOs\Document\SignatureDocumentFilterDTO;

interface SignatureDocumentRepositoryInterface
{
    public function getPaginatedDocuments(SignatureDocumentFilterDTO $filters): array;
    public function create(array $data): SignatureDocument;
    public function update(int $id, array $data, int $companyId): ?SignatureDocument;
    public function delete(int $id, int $companyId): bool;
    public function findById(int $id, int $companyId): ?SignatureDocument;
    public function hasDocumentAccess(SignatureDocument $document, \App\Models\User $user): bool;
}
