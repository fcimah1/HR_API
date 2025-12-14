<?php

namespace App\Services;

use App\DTOs\Transfer\CreateTransferDTO;
use App\DTOs\Transfer\TransferFilterDTO;
use App\DTOs\Transfer\ApproveRejectTransferDTO;
use App\DTOs\Transfer\UpdateTransferDTO;
use App\Models\Transfer;
use App\Models\User;
use App\Repository\Interface\TransferRepositoryInterface;
use App\Services\SimplePermissionService;
use App\Services\NotificationService;
use App\Enums\StringStatusEnum;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransferService
{
    public function __construct(
        protected TransferRepositoryInterface $transferRepository,
        protected SimplePermissionService $permissionService,
        protected NotificationService $notificationService,
    ) {}

    /**
     * الحصول على قائمة النقل مع التصفية
     */
    public function getPaginatedTransfers(TransferFilterDTO $filters, User $user): array
    {
        $filterData = $filters->toArray();

        if ($user->user_type == 'company') {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $filterData['company_id'] = $effectiveCompanyId;
        } else {
            $subordinateIds = $this->getSubordinateEmployeeIds($user);

            if (!empty($subordinateIds)) {
                $subordinateIds[] = $user->user_id;
                $filterData['employee_ids'] = $subordinateIds;
                $filterData['company_id'] = $user->company_id;
            } else {
                $filterData['employee_id'] = $user->user_id;
                $filterData['company_id'] = $user->company_id;
            }
        }

        $updatedFilters = TransferFilterDTO::fromRequest($filterData);

        return $this->transferRepository->getPaginatedTransfers($updatedFilters, $user);
    }

    /**
     * الحصول على جميع معرفات الموظفين التابعين
     */
    private function getSubordinateEmployeeIds(User $manager): array
    {
        $allEmployees = User::where('company_id', $manager->company_id)
            ->where('user_type', 'staff')
            ->get();

        $subordinateIds = [];

        foreach ($allEmployees as $employee) {
            if ($this->permissionService->canViewEmployeeRequests($manager, $employee)) {
                $subordinateIds[] = $employee->user_id;
            }
        }

        return $subordinateIds;
    }

    /**
     * الحصول على نقل بواسطة المعرف
     */
    public function getTransferById(int $id, ?int $companyId = null, ?int $userId = null, ?User $user = null): ?Transfer
    {
        $user = $user ?? Auth::user();

        if (is_null($companyId) && is_null($userId)) {
            throw new \InvalidArgumentException('يجب توفير معرف الشركة أو معرف المستخدم');
        }

        if ($companyId !== null) {
            $transfer = $this->transferRepository->findTransferById($id, $companyId);

            if ($user && $user->user_type !== 'company' && $transfer) {
                if ($transfer->employee_id === $user->user_id) {
                    return $transfer;
                }

                $employee = User::find($transfer->employee_id);
                if (!$employee || !$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    return null;
                }
            }
            return $transfer;
        }

        if ($userId !== null) {
            return $this->transferRepository->findTransferForEmployee($id, $userId);
        }

        return null;
    }

    /**
     * إنشاء نقل جديد
     */
    public function createTransfer(CreateTransferDTO $dto): Transfer
    {
        return DB::transaction(function () use ($dto) {
            Log::info('TransferService::createTransfer', [
                'employee_id' => $dto->employeeId,
            ]);

            $transfer = $this->transferRepository->createTransfer($dto);

            if (!$transfer) {
                throw new \Exception('فشل في إنشاء طلب النقل');
            }

            // إرسال إشعار للإدارة
            $this->notificationService->sendSubmissionNotification(
                'transfer_settings',
                (string)$transfer->transfer_id,
                $dto->companyId,
                StringStatusEnum::SUBMITTED->value,
                $dto->employeeId
            );

            return $transfer;
        });
    }

    /**
     * تحديث نقل
     */
    public function updateTransfer(int $id, UpdateTransferDTO $dto, User $user): Transfer
    {
        return DB::transaction(function () use ($id, $dto, $user) {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $transfer = $this->transferRepository->findTransferById($id, $effectiveCompanyId);

            if (!$transfer) {
                throw new \Exception('طلب النقل غير موجود');
            }

            if ($transfer->status !== Transfer::STATUS_PENDING) {
                throw new \Exception('لا يمكن تعديل طلب النقل بعد معالجته');
            }

            // فقط مدير الشركة أو من أضاف الطلب يمكنه التعديل
            $isAdder = $transfer->added_by === $user->user_id;
            if (!$isAdder && $user->user_type !== 'company') {
                throw new \Exception('ليس لديك صلاحية لتعديل هذا الطلب');
            }

            return $this->transferRepository->updateTransfer($transfer, $dto);
        });
    }

    /**
     * حذف نقل
     */
    public function deleteTransfer(int $id, User $user): bool
    {
        return DB::transaction(function () use ($id, $user) {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $transfer = $this->transferRepository->findTransferById($id, $effectiveCompanyId);

            if (!$transfer) {
                throw new \Exception('طلب النقل غير موجود');
            }

            if ($transfer->status !== Transfer::STATUS_PENDING) {
                throw new \Exception('لا يمكن حذف طلب النقل بعد معالجته');
            }

            $isAdder = $transfer->added_by === $user->user_id;
            if (!$isAdder && $user->user_type !== 'company') {
                throw new \Exception('ليس لديك صلاحية لحذف هذا الطلب');
            }

            return $this->transferRepository->deleteTransfer($transfer);
        });
    }

    /**
     * الموافقة أو رفض نقل
     */
    public function approveOrRejectTransfer(int $id, ApproveRejectTransferDTO $dto): Transfer
    {
        return DB::transaction(function () use ($id, $dto) {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $transfer = $this->transferRepository->findTransferById($id, $effectiveCompanyId);

            if (!$transfer) {
                throw new \Exception('طلب النقل غير موجود');
            }

            if ($transfer->status !== Transfer::STATUS_PENDING) {
                throw new \Exception('تم معالجة طلب النقل مسبقاً');
            }

            // التحقق من صلاحيات الموافقة
            if ($user->user_type !== 'company') {
                $employee = User::find($transfer->employee_id);
                if (!$employee || !$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    throw new \Exception('ليس لديك صلاحية لمعالجة طلب نقل هذا الموظف');
                }
            }

            if ($dto->action === 'approve') {
                $processedTransfer = $this->transferRepository->approveTransfer(
                    $transfer,
                    $dto->processedBy,
                    $dto->remarks
                );

                $this->notificationService->sendApprovalNotification(
                    'transfer_settings',
                    (string)$processedTransfer->transfer_id,
                    $effectiveCompanyId,
                    StringStatusEnum::APPROVED->value,
                    $dto->processedBy,
                    null,
                    $processedTransfer->employee_id
                );

                return $processedTransfer;
            } else {
                $processedTransfer = $this->transferRepository->rejectTransfer(
                    $transfer,
                    $dto->processedBy,
                    $dto->remarks
                );

                $this->notificationService->sendApprovalNotification(
                    'transfer_settings',
                    (string)$processedTransfer->transfer_id,
                    $effectiveCompanyId,
                    StringStatusEnum::REJECTED->value,
                    $dto->processedBy,
                    null,
                    $processedTransfer->employee_id
                );

                return $processedTransfer;
            }
        });
    }

    /**
     * الحصول على حالات النقل
     */
    public function getTransferStatuses(): array
    {
        return [
            ['value' => Transfer::STATUS_PENDING, 'label' => 'قيد المراجعة', 'label_en' => 'Pending'],
            ['value' => Transfer::STATUS_APPROVED, 'label' => 'موافق عليه', 'label_en' => 'Approved'],
            ['value' => Transfer::STATUS_REJECTED, 'label' => 'مرفوض', 'label_en' => 'Rejected'],
        ];
    }
}
