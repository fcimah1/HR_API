<?php

namespace App\Services;

use App\DTOs\Termination\CreateTerminationDTO;
use App\DTOs\Termination\UpdateTerminationDTO;
use App\Models\Termination;
use App\Models\User;
use App\Repository\Interface\TerminationRepositoryInterface;
use App\Services\FileUploadService;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TerminationService
{
    protected $terminationRepository;
    protected $fileUploadService;

    public function __construct(TerminationRepositoryInterface $terminationRepository, FileUploadService $fileUploadService)
    {
        $this->terminationRepository = $terminationRepository;
        $this->fileUploadService = $fileUploadService;
    }

    public function getTerminations(int $companyId, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->terminationRepository->getTerminations($companyId, $filters, $perPage);
    }

    public function createTermination(CreateTerminationDTO $dto): Termination
    {
        try {
            $data = $dto->toArray();

            // Handle document file upload
            if ($dto->documentFile) {

                $uploadResult = $this->fileUploadService->uploadDocument($dto->documentFile, $dto->employeeId, 'pdf_files/termination', 'termination');

                if ($uploadResult) {
                    $data['document_file'] = $uploadResult['filename'];
                }
            }
            return $this->terminationRepository->create($data);
        } catch (Exception $e) {
            Log::error('TerminationService@createTermination: Error creating termination', [
                'error' => $e->getMessage(),
                'dto' => $dto,
                'message' => 'حدث خطأ أثناء إضافة إشعار إنهاء الخدمة',
            ]);
            throw new \Exception('حدث خطأ أثناء إضافة إشعار إنهاء الخدمة');
        }
    }

    public function getTermination(int $id, int $companyId): ?Termination
    {
        return $this->terminationRepository->find($id, $companyId);
    }

    public function updateTermination(int $id, UpdateTerminationDTO $dto, int $companyId): ?Termination
    {
        try {
            $termination = $this->terminationRepository->find($id, $companyId);

            if (!$termination) {
                Log::error('TerminationService@updateTermination: Termination not found', [
                    'termination_id' => $id,
                    'company_id' => $companyId,
                    'message' => 'الطلب غير موجود',
                ]);
                throw new \Exception('الطلب غير موجود', 404);
            }

            // Lock if not pending
            if ($termination->status !== 0) {
                Log::error('TerminationService@updateTermination: Termination is not pending', [
                    'termination_id' => $id,
                    'company_id' => $companyId,
                    'status' => $termination->status,
                    'message' => 'لا يمكن تعديل طلب تم اعتماده أو رفضه مسبقاً',
                ]);
                throw new \Exception('لا يمكن تعديل طلب تم اعتماده أو رفضه مسبقاً', 400);
            }

            return DB::transaction(function () use ($termination, $dto) {
                // Handle document file upload
                $updatedTermination = $this->terminationRepository->update($termination, $dto->toArray());

                // If Status is updated to Approved (1), deactivate employee
                if ($dto->status === 1) {
                    $this->deactivateEmployee($updatedTermination->employee_id);
                }

                return $updatedTermination;
            });
        } catch (Exception $e) {
            Log::error('TerminationService@updateTermination: Error updating termination', [
                'error' => $e->getMessage(),
                'dto' => $dto,
                'message' => 'حدث خطأ أثناء تحديث إشعار إنهاء الخدمة',
            ]);
            throw new \Exception('حدث خطأ أثناء تحديث إشعار إنهاء الخدمة');
        }
    }

    public function deleteTermination(int $id, int $companyId): bool
    {
        try {
            $termination = $this->terminationRepository->find($id, $companyId);

            if (!$termination) {
                Log::error('TerminationService@deleteTermination: Termination not found', [
                    'termination_id' => $id,
                    'company_id' => $companyId,
                    'message' => 'الطلب غير موجود',
                ]);
                throw new \Exception('الطلب غير موجود', 404);
            }

            // Lock if not pending
            if ($termination->status !== 0) {
                Log::error('TerminationService@deleteTermination: Termination is not pending', [
                    'termination_id' => $id,
                    'company_id' => $companyId,
                    'status' => $termination->status,
                    'message' => 'لا يمكن حذف طلب تم اعتماده أو رفضه مسبقاً',
                ]);
                throw new \Exception('لا يمكن حذف طلب تم اعتماده أو رفضه مسبقاً', 400);
            }

            return $this->terminationRepository->delete($termination);
        } catch (Exception $e) {
            Log::error('TerminationService@deleteTermination: Error deleting termination', [
                'error' => $e->getMessage(),
                'termination_id' => $id,
                'company_id' => $companyId,
                'message' => 'حدث خطأ أثناء حذف إشعار إنهاء الخدمة',
            ]);
            throw new \Exception('حدث خطأ أثناء حذف إشعار إنهاء الخدمة');
        }
    }

    /**
     * Deactivate the employee account.
     */
    protected function deactivateEmployee(int $employeeId): void
    {
        try {
            $user = User::find($employeeId);
            if ($user) {
                $user->update(['is_active' => 0]);
                Log::info('Employee deactivated due to termination approval', [
                    'employee_id' => $employeeId,
                    'user_id' => Auth::id() ?? 'System' // Corrected Auth::id() context in final version
                ]);
            }
        } catch (Exception $e) {
            Log::error('TerminationService@deactivateEmployee: Error deactivating employee', [
                'error' => $e->getMessage(),
                'employee_id' => $employeeId,
                'message' => 'حدث خطأ أثناء إلغاء تفعيل الموظف',
            ]);
            throw new \Exception('حدث خطأ أثناء إلغاء تفعيل الموظف');
        }
    }
}
