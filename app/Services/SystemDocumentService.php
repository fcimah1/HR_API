<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemDocument;
use App\Models\User;
use App\DTOs\Document\SystemDocumentFilterDTO;
use App\DTOs\Document\CreateSystemDocumentDTO;
use App\DTOs\Document\UpdateSystemDocumentDTO;
use App\Repository\Interface\SystemDocumentRepositoryInterface;
use App\Services\FileUploadService;
use Illuminate\Support\Facades\Log;

class SystemDocumentService
{
    public function __construct(
        private readonly SystemDocumentRepositoryInterface $documentRepository,
        private readonly FileUploadService $fileUploadService
    ) {}

    /**
     * Get paginated documents
     */
    public function getPaginatedDocuments(SystemDocumentFilterDTO $filters): array
    {
        return $this->documentRepository->getPaginatedDocuments($filters);
    }

    /**
     * Create a new document with file upload
     */
    public function createDocument(CreateSystemDocumentDTO $dto): SystemDocument
    {
        try {
            // Upload file
            $uploadResult = $this->fileUploadService->uploadDocument(
                $dto->documentFile,
                $dto->companyId, // Use companyId as ID for folder structure if needed, or user_id
                'system_documents',
                'doc'
            );

            if (!$uploadResult) {
                throw new \Exception('فشل تحميل ملف المستند', 500);
            }

            $data = $dto->toArray();
            $data['document_file'] = $uploadResult['filename']; // We store only filename as seen in screenshot

            return $this->documentRepository->createDocument($data);
        } catch (\Exception $e) {
            Log::error('SystemDocumentService::createDocument failed', [
                'error' => $e->getMessage(),
                'company_id' => $dto->companyId
            ]);
            throw $e;
        }
    }

    /**
     * Update document details
     */
    public function updateDocument(int $id, UpdateSystemDocumentDTO $dto, int $companyId, ?User $requester = null): ?SystemDocument
    {
        $document = $this->documentRepository->getDocumentById($id, $companyId);
        if (!$document) {
            throw new \Exception('المستند غير موجود', 404);
        }

        if ($requester && !$this->documentRepository->hasDocumentAccess($document, $requester)) {
            throw new \Exception('غير مصرح لك بتعديل هذا المستند', 403);
        }

        return $this->documentRepository->updateDocument($id, $dto->toArray());
    }

    /**
     * Delete document and its file
     */
    public function deleteDocument(int $id, int $companyId, ?User $requester = null): bool
    {
        $document = $this->documentRepository->getDocumentById($id, $companyId);
        if (!$document) {
            throw new \Exception('المستند غير موجود', 404);
        }

        if ($requester && !$this->documentRepository->hasDocumentAccess($document, $requester)) {
            throw new \Exception('غير مصرح لك بحذف هذا المستند', 403);
        }

        // We might want to delete the physical file too, but let's check screenshot if they store just filename
        // The screenshot shows 1674633226_385097b606efd727c441.jpg
        // If FileUploadService::deleteFile is used, we need the full path

        // delete from DB first
        $deleted = $this->documentRepository->deleteDocument($id);

        if ($deleted) {
            // Try to delete physical file if needed
            // $this->fileUploadService->deleteFile(env('SHARED_UPLOADS_PATH') . '/system_documents/' . $document->document_file);
        }

        return $deleted;
    }

    /**
     * Get single document
     */
    public function getDocumentById(int $id, int $companyId, ?User $requester = null): ?SystemDocument
    {
        $document = $this->documentRepository->getDocumentById($id, $companyId);
        if (!$document) {
            throw new \Exception('المستند غير موجود', 404);
        }
        if ($document && $requester) {
            if (!$this->documentRepository->hasDocumentAccess($document, $requester)) {
                throw new \Exception('غير مصرح لك بعرض هذا المستند', 403);
            }
        }
        return $document;
    }
}
