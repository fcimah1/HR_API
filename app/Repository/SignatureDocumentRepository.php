<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\SignatureDocument;
use App\Repository\Interface\SignatureDocumentRepositoryInterface;
use App\DTOs\Document\SignatureDocumentFilterDTO;

class SignatureDocumentRepository implements SignatureDocumentRepositoryInterface
{
    public function getPaginatedDocuments(SignatureDocumentFilterDTO $filters): array
    {
        $query = SignatureDocument::query()
            ->with(['assignedStaff.employee'])
            ->where('company_id', $filters->companyId);

        if (!empty($filters->search)) {
            $query->where(function ($q) use ($filters) {
                $q->where('document_name', 'like', '%' . $filters->search . '%')
                    ->orWhere('document_file', 'like', '%' . $filters->search . '%');
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

    public function create(array $data): SignatureDocument
    {
        return SignatureDocument::create($data);
    }

    public function update(int $id, array $data, int $companyId): ?SignatureDocument
    {
        $document = $this->findById($id, $companyId);
        if (!$document) {
            return null;
        }

        $document->update($data);
        return $document;
    }

    public function delete(int $id, int $companyId): bool
    {
        $document = $this->findById($id, $companyId);
        if (!$document) {
            return false;
        }

        return (bool) $document->delete();
    }

    public function findById(int $id, int $companyId): ?SignatureDocument
    {
        return SignatureDocument::where('document_id', $id)
            ->with(['assignedStaff.employee'])
            ->where('company_id', $companyId)
            ->first();
    }
}
