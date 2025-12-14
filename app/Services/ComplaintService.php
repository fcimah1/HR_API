<?php

namespace App\Services;

use App\DTOs\Complaint\CreateComplaintDTO;
use App\DTOs\Complaint\ComplaintFilterDTO;
use App\DTOs\Complaint\ResolveComplaintDTO;
use App\DTOs\Complaint\UpdateComplaintDTO;
use App\Models\Complaint;
use App\Models\User;
use App\Repository\Interface\ComplaintRepositoryInterface;
use App\Services\SimplePermissionService;
use App\Services\NotificationService;
use App\Enums\StringStatusEnum;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ComplaintService
{
    public function __construct(
        protected ComplaintRepositoryInterface $complaintRepository,
        protected SimplePermissionService $permissionService,
        protected NotificationService $notificationService,
    ) {}

    /**
     * الحصول على قائمة الشكاوى مع التصفية
     */
    public function getPaginatedComplaints(ComplaintFilterDTO $filters, User $user): array
    {
        // إنشاء filters جديد بناءً على صلاحيات المستخدم
        $filterData = $filters->toArray();

        // التحقق من نوع المستخدم (company أو staff فقط)
        if ($user->user_type == 'company') {
            // مدير الشركة: يرى جميع شكاوى شركته
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $filterData['company_id'] = $effectiveCompanyId;
        } else {
            // موظف (staff): يرى شكاواه + شكاوى الموظفين التابعين له
            $subordinateIds = $this->getSubordinateEmployeeIds($user);

            if (!empty($subordinateIds)) {
                // لديه موظفين تابعين: شكاواه + شكاوى التابعين
                $subordinateIds[] = $user->user_id;
                $filterData['employee_ids'] = $subordinateIds;
                $filterData['company_id'] = $user->company_id;
            } else {
                // ليس لديه موظفين تابعين: شكاواه فقط
                $filterData['employee_id'] = $user->user_id;
                $filterData['company_id'] = $user->company_id;
            }
        }

        // إنشاء DTO جديد مع البيانات المحدثة
        $updatedFilters = ComplaintFilterDTO::fromRequest($filterData);

        return $this->complaintRepository->getPaginatedComplaints($updatedFilters, $user);
    }

    /**
     * الحصول على جميع معرفات الموظفين التابعين
     */
    private function getSubordinateEmployeeIds(User $manager): array
    {
        // الحصول على جميع الموظفين في نفس الشركة
        $allEmployees = User::where('company_id', $manager->company_id)
            ->where('user_type', 'staff')
            ->get();

        $subordinateIds = [];

        foreach ($allEmployees as $employee) {
            // التحقق إذا كان المدير يمكنه عرض طلبات هذا الموظف
            if ($this->permissionService->canViewEmployeeRequests($manager, $employee)) {
                $subordinateIds[] = $employee->user_id;
            }
        }

        return $subordinateIds;
    }

    /**
     * الحصول على شكوى بواسطة المعرف
     */
    public function getComplaintById(int $id, ?int $companyId = null, ?int $userId = null, ?User $user = null): ?Complaint
    {
        $user = $user ?? Auth::user();

        if (is_null($companyId) && is_null($userId)) {
            throw new \InvalidArgumentException('يجب توفير معرف الشركة أو معرف المستخدم');
        }

        // البحث عن الشكوى بواسطة معرف الشركة (للمستخدمين من نوع company/admins)
        if ($companyId !== null) {
            $complaint = $this->complaintRepository->findComplaintById($id, $companyId);

            // Check hierarchy permissions for staff users
            if ($user && $user->user_type !== 'company' && $complaint) {
                // Allow users to view their own complaints
                if ($complaint->complaint_from === $user->user_id) {
                    return $complaint;
                }

                $employee = User::find($complaint->complaint_from);
                if (!$employee || !$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    Log::warning('ComplaintService::getComplaintById - Hierarchy permission denied', [
                        'complaint_id' => $id,
                        'requester_id' => $user->user_id,
                        'complaint_from' => $complaint->complaint_from,
                    ]);
                    return null;
                }
            }
            return $complaint;
        }

        // البحث عن الشكوى بواسطة معرف المستخدم (للموظفين العاديين)
        if ($userId !== null) {
            return $this->complaintRepository->findComplaintForEmployee($id, $userId);
        }

        return null;
    }

    /**
     * إنشاء شكوى جديدة
     */
    public function createComplaint(CreateComplaintDTO $dto): Complaint
    {
        return DB::transaction(function () use ($dto) {
            Log::info('ComplaintService::createComplaint', [
                'complaint_from' => $dto->complaintFrom,
                'title' => $dto->title,
            ]);

            $complaint = $this->complaintRepository->createComplaint($dto);

            if (!$complaint) {
                throw new \Exception('فشل في إنشاء الشكوى');
            }

            // إرسال إشعار للإدارة
            $this->notificationService->sendSubmissionNotification(
                'complaint_settings',
                (string)$complaint->complaint_id,
                $dto->companyId,
                StringStatusEnum::SUBMITTED->value,
                $dto->complaintFrom
            );

            return $complaint;
        });
    }

    /**
     * تحديث شكوى
     */
    public function updateComplaint(int $id, UpdateComplaintDTO $dto, User $user): Complaint
    {
        return DB::transaction(function () use ($id, $dto, $user) {
            // الحصول على معرف الشركة الفعلي
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // البحث عن الشكوى
            $complaint = $this->complaintRepository->findComplaintById($id, $effectiveCompanyId);

            if (!$complaint) {
                throw new \Exception('الشكوى غير موجودة');
            }

            // التحقق من أن الشكوى قيد المراجعة
            if ($complaint->status !== Complaint::STATUS_PENDING) {
                throw new \Exception('لا يمكن تعديل الشكوى بعد معالجتها');
            }

            // التحقق من صلاحية التعديل (المالك فقط)
            $isOwner = $complaint->complaint_from === $user->user_id;

            if (!$isOwner) {
                throw new \Exception('ليس لديك صلاحية لتعديل هذه الشكوى');
            }

            // تحديث الشكوى
            $updatedComplaint = $this->complaintRepository->updateComplaint($complaint, $dto);

            Log::info('ComplaintService::updateComplaint', [
                'complaint_id' => $updatedComplaint->complaint_id,
                'updated_by' => $user->user_id,
            ]);

            return $updatedComplaint;
        });
    }

    /**
     * حذف شكوى
     */
    public function deleteComplaint(int $id, User $user): bool
    {
        return DB::transaction(function () use ($id, $user) {
            // الحصول على معرف الشركة الفعلي
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // البحث عن الشكوى
            $complaint = $this->complaintRepository->findComplaintById($id, $effectiveCompanyId);

            if (!$complaint) {
                throw new \Exception('الشكوى غير موجودة');
            }

            // التحقق من أن الشكوى قيد المراجعة
            if ($complaint->status !== Complaint::STATUS_PENDING) {
                throw new \Exception('لا يمكن حذف الشكوى بعد معالجتها');
            }

            // التحقق من صلاحية الحذف (المالك فقط أو مدير الشركة)
            $isOwner = $complaint->complaint_from === $user->user_id;
            $isCompanyAdmin = $user->user_type === 'company';

            if (!$isOwner && !$isCompanyAdmin) {
                throw new \Exception('ليس لديك صلاحية لحذف هذه الشكوى');
            }

            Log::info('ComplaintService::deleteComplaint', [
                'complaint_id' => $id,
                'deleted_by' => $user->user_id,
            ]);

            return $this->complaintRepository->deleteComplaint($complaint);
        });
    }

    /**
     * حل أو رفض شكوى
     */
    public function resolveOrRejectComplaint(int $id, ResolveComplaintDTO $dto): Complaint
    {
        return DB::transaction(function () use ($id, $dto) {
            $user = Auth::user();

            Log::info('ComplaintService::resolveOrRejectComplaint - Transaction started', [
                'complaint_id' => $id,
                'action' => $dto->action
            ]);

            // الحصول على معرف الشركة الفعلي
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // البحث عن الشكوى
            $complaint = $this->complaintRepository->findComplaintById($id, $effectiveCompanyId);

            if (!$complaint) {
                throw new \Exception('الشكوى غير موجودة');
            }

            // التحقق من أن الشكوى قيد المراجعة
            if ($complaint->status !== Complaint::STATUS_PENDING) {
                throw new \Exception('تم معالجة هذه الشكوى مسبقاً');
            }

            // Check hierarchy permissions for staff users
            if ($user->user_type !== 'company') {
                $employee = User::find($complaint->complaint_from);
                if (!$employee || !$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    Log::warning('ComplaintService::resolveOrRejectComplaint - Hierarchy permission denied', [
                        'complaint_id' => $id,
                        'requester_id' => $user->user_id,
                        'complaint_from' => $complaint->complaint_from,
                    ]);
                    throw new \Exception('ليس لديك صلاحية لمعالجة شكوى هذا الموظف');
                }
            }

            if ($dto->action === 'resolve') {
                // حل الشكوى
                $processedComplaint = $this->complaintRepository->resolveComplaint(
                    $complaint,
                    $dto->processedBy,
                    $dto->remarks
                );

                // إرسال إشعار للموظف
                $this->notificationService->sendApprovalNotification(
                    'complaint_settings',
                    (string)$processedComplaint->complaint_id,
                    $effectiveCompanyId,
                    StringStatusEnum::APPROVED->value,
                    $dto->processedBy,
                    null,
                    $processedComplaint->complaint_from
                );

                Log::info('ComplaintService::resolveOrRejectComplaint - Complaint resolved', [
                    'complaint_id' => $id,
                ]);

                return $processedComplaint;
            } else {
                // رفض الشكوى
                $processedComplaint = $this->complaintRepository->rejectComplaint(
                    $complaint,
                    $dto->processedBy,
                    $dto->remarks
                );

                // إرسال إشعار للموظف
                $this->notificationService->sendApprovalNotification(
                    'complaint_settings',
                    (string)$processedComplaint->complaint_id,
                    $effectiveCompanyId,
                    StringStatusEnum::REJECTED->value,
                    $dto->processedBy,
                    null,
                    $processedComplaint->complaint_from
                );

                Log::info('ComplaintService::resolveOrRejectComplaint - Complaint rejected', [
                    'complaint_id' => $id,
                ]);

                return $processedComplaint;
            }
        });
    }

    /**
     * الحصول على الحالات المتاحة للشكاوى
     */
    public function getComplaintStatuses(): array
    {
        return [
            ['value' => Complaint::STATUS_PENDING, 'label' => 'قيد المراجعة', 'label_en' => 'Pending'],
            ['value' => Complaint::STATUS_RESOLVED, 'label' => 'تم الحل', 'label_en' => 'Resolved'],
            ['value' => Complaint::STATUS_REJECTED, 'label' => 'مرفوض', 'label_en' => 'Rejected'],
        ];
    }
}
