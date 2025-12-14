<?php

namespace App\Services;

use App\DTOs\Resignation\CreateResignationDTO;
use App\DTOs\Resignation\ResignationFilterDTO;
use App\DTOs\Resignation\ApproveRejectResignationDTO;
use App\DTOs\Resignation\UpdateResignationDTO;
use App\Models\Resignation;
use App\Models\User;
use App\Repository\Interface\ResignationRepositoryInterface;
use App\Services\SimplePermissionService;
use App\Services\NotificationService;
use App\Enums\StringStatusEnum;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResignationService
{
    public function __construct(
        protected ResignationRepositoryInterface $resignationRepository,
        protected SimplePermissionService $permissionService,
        protected NotificationService $notificationService,
    ) {}

    /**
     * الحصول على قائمة الاستقالات مع التصفية
     */
    public function getPaginatedResignations(ResignationFilterDTO $filters, User $user): array
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

        $updatedFilters = ResignationFilterDTO::fromRequest($filterData);

        return $this->resignationRepository->getPaginatedResignations($updatedFilters, $user);
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
     * الحصول على استقالة بواسطة المعرف
     */
    public function getResignationById(int $id, ?int $companyId = null, ?int $userId = null, ?User $user = null): ?Resignation
    {
        $user = $user ?? Auth::user();

        if (is_null($companyId) && is_null($userId)) {
            throw new \InvalidArgumentException('يجب توفير معرف الشركة أو معرف المستخدم');
        }

        if ($companyId !== null) {
            $resignation = $this->resignationRepository->findResignationById($id, $companyId);

            if ($user && $user->user_type !== 'company' && $resignation) {
                if ($resignation->employee_id === $user->user_id) {
                    return $resignation;
                }

                $employee = User::find($resignation->employee_id);
                if (!$employee || !$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    return null;
                }
            }
            return $resignation;
        }

        if ($userId !== null) {
            return $this->resignationRepository->findResignationForEmployee($id, $userId);
        }

        return null;
    }

    /**
     * إنشاء استقالة جديدة
     */
    public function createResignation(CreateResignationDTO $dto): Resignation
    {
        return DB::transaction(function () use ($dto) {
            Log::info('ResignationService::createResignation', [
                'employee_id' => $dto->employeeId,
            ]);

            $resignation = $this->resignationRepository->createResignation($dto);

            if (!$resignation) {
                throw new \Exception('فشل في إنشاء طلب الاستقالة');
            }

            // إرسال إشعار للإدارة
            $this->notificationService->sendSubmissionNotification(
                'resignation_settings',
                (string)$resignation->resignation_id,
                $dto->companyId,
                StringStatusEnum::SUBMITTED->value,
                $dto->employeeId
            );

            return $resignation;
        });
    }

    /**
     * تحديث استقالة
     */
    public function updateResignation(int $id, UpdateResignationDTO $dto, User $user): Resignation
    {
        return DB::transaction(function () use ($id, $dto, $user) {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $resignation = $this->resignationRepository->findResignationById($id, $effectiveCompanyId);

            if (!$resignation) {
                throw new \Exception('طلب الاستقالة غير موجود');
            }

            if ($resignation->status !== Resignation::STATUS_PENDING) {
                throw new \Exception('لا يمكن تعديل طلب الاستقالة بعد معالجته');
            }

            $isOwner = $resignation->employee_id === $user->user_id;
            if (!$isOwner && $user->user_type !== 'company') {
                throw new \Exception('ليس لديك صلاحية لتعديل هذا الطلب');
            }

            return $this->resignationRepository->updateResignation($resignation, $dto);
        });
    }

    /**
     * حذف استقالة
     */
    public function deleteResignation(int $id, User $user): bool
    {
        return DB::transaction(function () use ($id, $user) {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $resignation = $this->resignationRepository->findResignationById($id, $effectiveCompanyId);

            if (!$resignation) {
                throw new \Exception('طلب الاستقالة غير موجود');
            }

            if ($resignation->status !== Resignation::STATUS_PENDING) {
                throw new \Exception('لا يمكن حذف طلب الاستقالة بعد معالجته');
            }

            $isOwner = $resignation->employee_id === $user->user_id;
            if (!$isOwner && $user->user_type !== 'company') {
                throw new \Exception('ليس لديك صلاحية لحذف هذا الطلب');
            }

            return $this->resignationRepository->deleteResignation($resignation);
        });
    }

    /**
     * الموافقة أو رفض استقالة
     */
    public function approveOrRejectResignation(int $id, ApproveRejectResignationDTO $dto): Resignation
    {
        return DB::transaction(function () use ($id, $dto) {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $resignation = $this->resignationRepository->findResignationById($id, $effectiveCompanyId);

            if (!$resignation) {
                throw new \Exception('طلب الاستقالة غير موجود');
            }

            if ($resignation->status !== Resignation::STATUS_PENDING) {
                throw new \Exception('تم معالجة طلب الاستقالة مسبقاً');
            }

            // التحقق من صلاحيات الموافقة
            if ($user->user_type !== 'company') {
                $employee = User::find($resignation->employee_id);
                if (!$employee || !$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    throw new \Exception('ليس لديك صلاحية لمعالجة طلب استقالة هذا الموظف');
                }
            }

            if ($dto->action === 'approve') {
                $processedResignation = $this->resignationRepository->approveResignation(
                    $resignation,
                    $dto->processedBy,
                    $dto->remarks
                );

                $this->notificationService->sendApprovalNotification(
                    'resignation_settings',
                    (string)$processedResignation->resignation_id,
                    $effectiveCompanyId,
                    StringStatusEnum::APPROVED->value,
                    $dto->processedBy,
                    null,
                    $processedResignation->employee_id
                );

                return $processedResignation;
            } else {
                $processedResignation = $this->resignationRepository->rejectResignation(
                    $resignation,
                    $dto->processedBy,
                    $dto->remarks
                );

                $this->notificationService->sendApprovalNotification(
                    'resignation_settings',
                    (string)$processedResignation->resignation_id,
                    $effectiveCompanyId,
                    StringStatusEnum::REJECTED->value,
                    $dto->processedBy,
                    null,
                    $processedResignation->employee_id
                );

                return $processedResignation;
            }
        });
    }

    /**
     * الحصول على حالات الاستقالات
     */
    public function getResignationStatuses(): array
    {
        return [
            ['value' => Resignation::STATUS_PENDING, 'label' => 'قيد المراجعة', 'label_en' => 'Pending'],
            ['value' => Resignation::STATUS_APPROVED, 'label' => 'موافق عليها', 'label_en' => 'Approved'],
            ['value' => Resignation::STATUS_REJECTED, 'label' => 'مرفوضة', 'label_en' => 'Rejected'],
        ];
    }
}
