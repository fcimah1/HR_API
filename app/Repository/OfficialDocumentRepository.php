<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTOs\Document\OfficialDocumentFilterDTO;
use App\Models\OfficialDocument;
use App\Repository\Interface\OfficialDocumentRepositoryInterface;

class OfficialDocumentRepository implements OfficialDocumentRepositoryInterface
{
    public function getPaginatedDocuments(OfficialDocumentFilterDTO $filters): array
    {
        $query = OfficialDocument::query()
            ->where('company_id', $filters->companyId);

        if (!empty($filters->search)) {
            $query->where(function ($q) use ($filters) {
                $q->where('license_name', 'like', '%' . $filters->search . '%')
                    ->orWhere('document_type', 'like', '%' . $filters->search . '%')
                    ->orWhere('license_no', 'like', '%' . $filters->search . '%');
            });
        }

        $paginator = $query->orderBy('document_id', 'desc')
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    public function create(array $data): OfficialDocument
    {
        return OfficialDocument::create($data);
    }

    public function update(int $id, array $data, int $companyId): ?OfficialDocument
    {
        $document = $this->findById($id, $companyId);
        if ($document) {
            $document->update($data);
            return $document->fresh();
        }
        return null;
    }

    public function delete(int $id, int $companyId): bool
    {
        $document = $this->findById($id, $companyId);
        if ($document) {
            return $document->delete();
        }
        return false;
    }

    public function findById(int $id, int $companyId): ?OfficialDocument
    {
        return OfficialDocument::where('document_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }
}
