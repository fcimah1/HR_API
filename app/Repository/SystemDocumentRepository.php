<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\SystemDocument;
use App\DTOs\Document\SystemDocumentFilterDTO;
use App\Repository\Interface\SystemDocumentRepositoryInterface;
use Illuminate\Support\Facades\DB;

class SystemDocumentRepository implements SystemDocumentRepositoryInterface
{
    /**
     * Get paginated documents with filters
     */
    public function getPaginatedDocuments(SystemDocumentFilterDTO $filters): array
    {
        $query = SystemDocument::where('company_id', $filters->companyId)
            ->with(['department']);

        if ($filters->departmentId !== null) {
            $query->where('department_id', $filters->departmentId);
        }

        if ($filters->search !== null && trim($filters->search) !== '') {
            $searchTerm = '%' . $filters->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('document_name', 'like', $searchTerm)
                    ->orWhere('document_type', 'like', $searchTerm);
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

    /**
     * Create a new document record
     */
    public function createDocument(array $data): SystemDocument
    {
        return SystemDocument::create($data);
    }

    /**
     * Update an existing document record
     */
    public function updateDocument(int $id, array $data): ?SystemDocument
    {
        $document = SystemDocument::find($id);
        if ($document) {
            $document->update($data);
            return $document->fresh();
        }
        return null;
    }

    /**
     * Delete a document record
     */
    public function deleteDocument(int $id): bool
    {
        $document = SystemDocument::find($id);
        if ($document) {
            return (bool) $document->delete();
        }
        return false;
    }

    /**
     * Get a single document by ID and company
     */
    public function getDocumentById(int $id, int $companyId): ?SystemDocument
    {
        return SystemDocument::where('document_id', $id)
            ->where('company_id', $companyId)
            ->first();
    }
}
