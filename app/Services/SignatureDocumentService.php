<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SignatureDocument;
use App\DTOs\Document\SignatureDocumentFilterDTO;
use App\DTOs\Document\CreateSignatureDocumentDTO;
use App\DTOs\Document\UpdateSignatureDocumentDTO;
use App\Repository\Interface\SignatureDocumentRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SignatureDocumentService
{
    public function __construct(
        private readonly SignatureDocumentRepositoryInterface $repository,
        private readonly FileUploadService $fileUploadService
    ) {}

    public function getPaginatedDocuments(SignatureDocumentFilterDTO $filters): array
    {
        return $this->repository->getPaginatedDocuments($filters);
    }

    public function createDocument(CreateSignatureDocumentDTO $dto): SignatureDocument
    {
        return DB::transaction(function () use ($dto) {
            $data = $dto->toArray();

            // Handle file upload
            $uploadResult = $this->fileUploadService->uploadDocument(
                $dto->documentFile,
                $dto->companyId,
                'pdf_files/files',
                'sig'
            );

            if (!$uploadResult) {
                Log::error('SignatureDocumentService::createDocument failed', [
                    'companyId' => $dto->companyId,
                    'error' => 'فشل في رفع ملف التوقيع'
                ]);
                throw new \Exception('فشل في رفع ملف التوقيع');
            }

            $data['document_file'] = $uploadResult['filename'];
            $data['document_size'] = (string) $dto->documentFile->getSize();

            $document = $this->repository->create($data);

            // Handle Staff Assignments
            $staffIds = $dto->staffIds;
            if ($dto->shareWithEmployees === 'all') {
                // Fetch all active staff in company
                $staffIds = \App\Models\User::where('company_id', $dto->companyId)
                    ->where('user_type', 'staff')
                    ->where('is_active', 1)
                    ->pluck('user_id')
                    ->toArray();
            }

            if (!empty($staffIds)) {
                $assignments = [];
                $now = date('Y-m-d H:i:s');
                foreach ($staffIds as $staffId) {
                    $assignments[] = [
                        'company_id' => $dto->companyId,
                        'staff_id' => $staffId,
                        'signature_file_id' => $document->document_id,
                        'signature_task' => (string) $dto->signatureTask,
                        'is_signed' => 0,
                        'signed_file' => null,
                        'signed_date' => null,
                        'created_at' => $now,
                    ];
                }

                \App\Models\StaffSignatureDocument::insert($assignments);
            }

            return $document;
        });
    }

    public function updateDocument(int $id, UpdateSignatureDocumentDTO $dto, int $companyId, ?\App\Models\User $requester = null): ?SignatureDocument
    {
        $document = $this->repository->findById($id, $companyId);
        if (!$document) {
            throw new \Exception('المستند غير موجود', 404);
        }

        if ($requester && !$this->repository->hasDocumentAccess($document, $requester)) {
            throw new \Exception('غير مصرح لك بتعديل هذا المستند', 403);
        }

        $data = $dto->toArray();

        return $this->repository->update($id, $data, $companyId);
    }

    public function deleteDocument(int $id, int $companyId, ?\App\Models\User $requester = null): bool
    {
        $document = $this->repository->findById($id, $companyId);
        if (!$document) {
            throw new \Exception('المستند غير موجود', 404);
        }

        if ($requester && !$this->repository->hasDocumentAccess($document, $requester)) {
            throw new \Exception('غير مصرح لك بحذف هذا المستند', 403);
        }

        return $this->repository->delete($id, $companyId);
    }

    public function getDocumentById(int $id, int $companyId, ?\App\Models\User $requester = null): ?SignatureDocument
    {
        $document = $this->repository->findById($id, $companyId);
        
        if (!$document) {
            throw new \Exception('المستند غير موجود', 404);
        }
        
        if ($document && $requester && !$this->repository->hasDocumentAccess($document, $requester)) {
            throw new \Exception('غير مصرح لك بعرض هذا المستند', 403);
        }

        return $document;
    }
}
