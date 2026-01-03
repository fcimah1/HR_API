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
use App\Services\ApprovalService;
use App\Enums\StringStatusEnum;
use App\Jobs\SendEmailNotificationJob;
use App\Mail\Complaint\ComplaintSubmitted;
use App\Mail\Complaint\ComplaintResolved;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class ComplaintService
{
    public function __construct(
        protected ComplaintRepositoryInterface $complaintRepository,
        protected SimplePermissionService $permissionService,
        protected NotificationService $notificationService,
        protected ApprovalService $approvalService,
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
            // الحفاظ على employee_id من الـ request إذا كان موجوداً (للتصفية)
            // (employee_id محفوظ بالفعل من $filters->toArray())
        } else {
            // موظف (staff): يرى شكاواه + شكاوى الموظفين التابعين له
            $subordinateIds = $this->getSubordinateEmployeeIds($user);

            if (!empty($subordinateIds)) {
                // لديه موظفين تابعين: شكاواه + شكاوى التابعين
                $subordinateIds[] = $user->user_id;

                // إذا تم تحديد employee_id في الـ request، تحقق أنه ضمن التابعين
                if (isset($filterData['employee_id']) && $filterData['employee_id'] !== null) {
                    if (!in_array($filterData['employee_id'], $subordinateIds)) {
                        // الموظف المطلوب ليس ضمن التابعين - لا نعرض شيء
                        $filterData['employee_id'] = null;
                        $filterData['employee_ids'] = [];
                    }
                } else {
                    $filterData['employee_ids'] = $subordinateIds;
                }
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
                Log::warning('ComplaintService::createComplaint - Failed to create complaint', [
                    'complaint_from' => $dto->complaintFrom,
                    'title' => $dto->title,
                    'message' => 'Failed to create complaint',
                ]);
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

            // Send email notification
            $employee = User::find($dto->complaintFrom);
            if ($employee && $employee->email) {
                SendEmailNotificationJob::dispatch(
                    new ComplaintSubmitted(
                        employeeName: $employee->full_name ?? $employee->first_name,
                        complaintType: $dto->complaintType ?? 'شكوى عامة',
                        complaintSubject: $dto->title ?? 'غير محدد',
                        description: $dto->description ?? null
                    ),
                    $employee->email
                );
            }

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
                Log::warning('ComplaintService::updateComplaint - Complaint not found', [
                    'complaint_id' => $id,
                    'requester_id' => $user->user_id,
                    'message' => 'الشكوى غير موجودة'
                ]);
                throw new \Exception('الشكوى غير موجودة');
            }

            // التحقق من أن الشكوى قيد المراجعة
            if ($complaint->status !== Complaint::STATUS_PENDING) {
                Log::warning('ComplaintService::updateComplaint - Complaint not pending', [
                    'complaint_id' => $id,
                    'requester_id' => $user->user_id,
                    'message' => 'لا يمكن تعديل الشكوى بعد معالجتها'
                ]);
                throw new \Exception('لا يمكن تعديل الشكوى بعد معالجتها');
            }

            // صلاحية التعديل: صاحب الشكوى، مدير الشركة، أو من لديه صلاحية رؤية طلبات الموظف
            $isOwner = $complaint->complaint_from === $user->user_id;
            $isCompany = $user->user_type === 'company';

            if (!$isOwner && !$isCompany) {
                $employee = User::find($complaint->complaint_from);
                if (!$employee || !$this->permissionService->canApproveEmployeeRequests($user, $employee)) {
                    Log::warning('ComplaintService::updateComplaint - Permission denied', [
                        'complaint_id' => $id,
                        'requester_id' => $user->user_id,
                        'message' => 'ليس لديك صلاحية لتعديل هذه الشكوى'
                    ]);
                    throw new \Exception('ليس لديك صلاحية لتعديل هذه الشكوى');
                }
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
    public function deleteComplaint(int $id, User $user, int $processedBy, ?string $description = null): bool
    {
        return DB::transaction(function () use ($id, $user, $processedBy, $description) {
            // Get effective company ID
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // Find complaint
            $complaint = $this->complaintRepository->findComplaintById($id, $effectiveCompanyId);

            if (!$complaint) {
                Log::warning('ComplaintService::deleteComplaint - Complaint not found', [
                    'complaint_id' => $id,
                    'message' => 'الشكوى غير موجودة',
                    'deleted_by' => $user->user_id,
                ]);
                throw new \Exception('الشكوى غير موجودة');
            }

            // 1. Status Check: Only Pending can be deleted
            if ($complaint->status !== Complaint::STATUS_PENDING) {
                Log::warning('ComplaintService::deleteComplaint - Complaint not pending', [
                    'complaint_id' => $id,
                    'message' => 'لا يمكن حذف الشكوى بعد معالجتها',
                    'deleted_by' => $user->user_id,
                ]);
                throw new \Exception('لا يمكن حذف الشكوى بعد معالجتها');
            }

            // 2. Permission Check
            $isOwner = $complaint->complaint_from === $user->user_id;
            $isCompany = $user->user_type === 'company';

            // Check hierarchy permission (is a manager of the employee)
            $isHierarchyManager = false;
            if (!$isOwner && !$isCompany) {
                $employee = User::find($complaint->complaint_from);
                if ($employee && $this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    $isHierarchyManager = true;
                }
            }

            if (!$isOwner && !$isCompany && !$isHierarchyManager) {
                Log::warning('ComplaintService::deleteComplaint - Permission denied', [
                    'complaint_id' => $id,
                    'message' => 'ليس لديك صلاحية لحذف هذه الشكوى',
                    'deleted_by' => $user->user_id,
                ]);
                throw new \Exception('ليس لديك صلاحية لحذف هذه الشكوى');
            }

            return $this->complaintRepository->deleteComplaint(
                $complaint,
                $processedBy,
                $description,
            );;
        });
    }

    /**
     * حل أو رفض شكوى
     */
    public function resolveOrRejectComplaint(int $id, ResolveComplaintDTO $dto): Complaint
    {
        return DB::transaction(function () use ($id, $dto) {
            $user = Auth::user();

            // الحصول على معرف الشركة الفعلي
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // البحث عن الشكوى
            $complaint = $this->complaintRepository->findComplaintById($id, $effectiveCompanyId);

            if (!$complaint) {
                Log::warning('ComplaintService::resolveOrRejectComplaint - Complaint not found', [
                    'complaint_id' => $id,
                    'message' => 'الشكوى غير موجودة',
                    'resolved_by' => $user->user_id,
                ]);
                throw new \Exception('الشكوى غير موجودة');
            }

            // التحقق من أن الشكوى قيد المراجعة
            if ($complaint->status !== Complaint::STATUS_PENDING) {
                Log::warning('ComplaintService::resolveOrRejectComplaint - Complaint not pending', [
                    'complaint_id' => $id,
                    'message' => 'لا يمكن معالجة الشكوى بعد معالجتها',
                    'resolved_by' => $user->user_id,
                ]);
                throw new \Exception('تم معالجة هذه الشكوى مسبقاً');
            }

            // Check hierarchy permissions for staff users (strict: must be higher level)
            if ($user->user_type !== 'company') {
                $employee = User::find($complaint->complaint_from);
                if (!$employee || !$this->permissionService->canApproveEmployeeRequests($user, $employee)) {
                    Log::warning('ComplaintService::resolveOrRejectComplaint - Hierarchy permission denied', [
                        'complaint_id' => $id,
                        'message' => 'ليس لديك صلاحية لمعالجة شكوى هذا الموظف',
                        'resolved_by' => $user->user_id,
                    ]);
                    throw new \Exception('ليس لديك صلاحية لمعالجة شكوى هذا الموظف');
                }
            }

            $userType = strtolower(trim($user->user_type ?? ''));

            // Company user can resolve/reject directly
            if ($userType === 'company') {
                if ($dto->action === 'resolve') {
                    $processedComplaint = $this->complaintRepository->resolveComplaint(
                        $complaint,
                        $dto->processedBy,
                        $dto->description,
                    );

                    // Record final approval
                    $this->approvalService->recordApproval(
                        $processedComplaint->complaint_id,
                        $dto->processedBy,
                        1, // approved
                        1, // final level
                        'complaint_settings',
                        $effectiveCompanyId
                    );

                    $this->notificationService->sendApprovalNotification(
                        'complaint_settings',
                        (string)$processedComplaint->complaint_id,
                        $effectiveCompanyId,
                        StringStatusEnum::APPROVED->value,
                        $dto->processedBy,
                        null,
                        $processedComplaint->complaint_from
                    );

                    // Send resolution email
                    $employee = User::find($processedComplaint->complaint_from);
                    if ($employee && $employee->email) {
                        SendEmailNotificationJob::dispatch(
                            new ComplaintResolved(
                                employeeName: $employee->full_name ?? $employee->first_name,
                                complaintType: $processedComplaint->complaint_type ?? 'شكوى عامة',
                                complaintSubject: $processedComplaint->title ?? 'غير محدد',
                                resolution: $dto->description ?? 'تم الحل',
                                remarks: null
                            ),
                            $employee->email
                        );
                    }

                    return $processedComplaint;
                } else {
                    // Company rejection
                    $processedComplaint = $this->complaintRepository->rejectComplaint(
                        $complaint,
                        $dto->processedBy,
                        $dto->description
                    );

                    // Record rejection
                    $this->approvalService->recordApproval(
                        $processedComplaint->complaint_id,
                        $dto->processedBy,
                        2, // rejected
                        2, // rejection level
                        'complaint_settings',
                        $effectiveCompanyId
                    );

                    $this->notificationService->sendApprovalNotification(
                        'complaint_settings',
                        (string)$processedComplaint->complaint_id,
                        $effectiveCompanyId,
                        StringStatusEnum::REJECTED->value,
                        $dto->processedBy,
                        null,
                        $processedComplaint->complaint_from
                    );

                    return $processedComplaint;
                }
            }

            // For staff users, verify approval levels
            $canApprove = $this->approvalService->canUserApprove(
                $user->user_id,
                $complaint->complaint_id,
                $complaint->complaint_from,
                'complaint_settings'
            );

            if (!$canApprove) {
                $denialInfo = $this->approvalService->getApprovalDenialReason(
                    $user->user_id,
                    $complaint->complaint_id,
                    $complaint->complaint_from,
                    'complaint_settings'
                );
                Log::warning('ComplaintService::approveOrRejectComplaint - Approval denied', [
                    'complaint_id' => $complaint->complaint_id,
                    'message' => $denialInfo['message'],
                    'approved_by' => $user->user_id,
                ]);
                throw new \Exception($denialInfo['message']);
            }

            if ($dto->action === 'resolve') {
                // Check if this is the final approval
                $isFinal = $this->approvalService->isFinalApproval(
                    $complaint->complaint_id,
                    $complaint->complaint_from,
                    'complaint_settings'
                );

                if ($isFinal) {
                    // Final approval
                    $processedComplaint = $this->complaintRepository->resolveComplaint(
                        $complaint,
                        $dto->processedBy,
                        $dto->description,
                    );

                    // Record final approval
                    $this->approvalService->recordApproval(
                        $processedComplaint->complaint_id,
                        $dto->processedBy,
                        1,
                        1,
                        'complaint_settings',
                        $effectiveCompanyId
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

                    // Send resolution email
                    $employee = User::find($processedComplaint->complaint_from);
                    if ($employee && $employee->email) {
                        SendEmailNotificationJob::dispatch(
                            new ComplaintResolved(
                                employeeName: $employee->full_name ?? $employee->first_name,
                                complaintType: $processedComplaint->complaint_type ?? 'شكوى عامة',
                                complaintSubject: $processedComplaint->title ?? 'غير محدد',
                                resolution: $dto->description ?? 'تم الحل',
                                remarks: null
                            ),
                            $employee->email
                        );
                    }

                    Log::warning('ComplaintService::resolveOrRejectComplaint - Complaint resolved', [
                        'complaint_id' => $id,
                        'message' => 'تم معالجة الشكوى بنجاح',
                        'resolved_by' => $user->user_id,
                    ]);

                    return $processedComplaint;
                } else {
                    // Intermediate approval - just record it, don't change status
                    $this->approvalService->recordApproval(
                        $complaint->complaint_id,
                        $dto->processedBy,
                        1, // approved
                        0, // intermediate level
                        'complaint_settings',
                        $effectiveCompanyId
                    );

                    // Send intermediate approval notification
                    $this->notificationService->sendApprovalNotification(
                        'complaint_settings',
                        (string)$complaint->complaint_id,
                        $effectiveCompanyId,
                        StringStatusEnum::APPROVED->value,
                        $dto->processedBy,
                        1,
                        $complaint->complaint_from
                    );

                    // Reload to get updated approvals
                    $complaint->refresh();

                    return $complaint;
                }
            } else {
                // رفض الشكوى
                $processedComplaint = $this->complaintRepository->rejectComplaint(
                    $complaint,
                    $dto->processedBy,
                    $dto->description
                );

                // Record rejection
                $this->approvalService->recordApproval(
                    $processedComplaint->complaint_id,
                    $dto->processedBy,
                    2, // rejected
                    2, // rejection level
                    'complaint_settings',
                    $effectiveCompanyId
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

                Log::warning('ComplaintService::resolveOrRejectComplaint - Complaint rejected', [
                    'complaint_id' => $id,
                    'message' => 'تم رفض الشكوى بنجاح',
                    'rejected_by' => $user->user_id,
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
