<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Document\CreateOfficialDocumentDTO;
use App\DTOs\Document\OfficialDocumentFilterDTO;
use App\DTOs\Document\UpdateOfficialDocumentDTO;
use App\Models\OfficialDocument;
use App\Repository\Interface\OfficialDocumentRepositoryInterface;
use Illuminate\Support\Facades\Log;

class OfficialDocumentService
{
    public function __construct(
        private readonly OfficialDocumentRepositoryInterface $repository,
        private readonly FileUploadService $fileUploadService
    ) {}

    public function getPaginatedDocuments(OfficialDocumentFilterDTO $filters): array
    {
        return $this->repository->getPaginatedDocuments($filters);
    }

    public function createDocument(CreateOfficialDocumentDTO $dto): OfficialDocument
    {
        try {
            $data = $dto->toArray();

            // Handle file upload
            $uploadResult = $this->fileUploadService->uploadDocument(
                $dto->documentFile,
                $dto->companyId,
                'official_documents',
                'license'
            );

            if (!$uploadResult) {
                Log::error('OfficialDocumentService::createDocument failed', [
                    'company_id' => $dto->companyId,
                    'error' => 'فشل في رفع ملف المستند'
                ]);
                throw new \Exception('فشل في رفع ملف المستند');
            }

            $data['document_file'] = $uploadResult['filename'];

            return $this->repository->create($data);
        } catch (\Exception $e) {
            Log::error('OfficialDocumentService::createDocument failed', [
                'company_id' => $dto->companyId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function updateDocument(int $id, UpdateOfficialDocumentDTO $dto, int $companyId): ?OfficialDocument
    {
        try {
            $data = $dto->toArray();

            // Handle optional file upload
            if ($dto->documentFile) {
                $uploadResult = $this->fileUploadService->uploadDocument(
                    $dto->documentFile,
                    $companyId,
                    'official_documents',
                    'license'
                );

                if (!$uploadResult) {
                    Log::error('OfficialDocumentService::updateDocument failed', [
                        'company_id' => $companyId,
                        'error' => 'فشل في رفع ملف المستند الجديد'
                    ]);
                    throw new \Exception('فشل في رفع ملف المستند الجديد');
                }

                $data['document_file'] = $uploadResult['filename'];
            }

            return $this->repository->update($id, $data, $companyId);
        } catch (\Exception $e) {
            Log::error('OfficialDocumentService::updateDocument failed', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function deleteDocument(int $id, int $companyId): bool
    {
        // Optionially delete the physical file here if needed
        return $this->repository->delete($id, $companyId);
    }

    public function getDocumentById(int $id, int $companyId): ?OfficialDocument
    {
        return $this->repository->findById($id, $companyId);
    }
}
