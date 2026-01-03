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
use App\Services\ApprovalService;
use App\Enums\StringStatusEnum;
use App\Jobs\SendEmailNotificationJob;
use App\Mail\Resignation\ResignationSubmitted;
use App\Mail\Resignation\ResignationApproved;
use App\Mail\Resignation\ResignationRejected;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class ResignationService
{
    public function __construct(
        protected ResignationRepositoryInterface $resignationRepository,
        protected SimplePermissionService $permissionService,
        protected NotificationService $notificationService,
        protected CacheService $cacheService,
        protected ApprovalService $approvalService,
    ) {}

    /**
     * الحصول على قائمة الاستقالات مع التصفية
     */
    public function getPaginatedResignations(ResignationFilterDTO $filters, User $user): array
    {
        $filterData = $filters->toArray();

        if ($user->user_type == 'company') {
            // Company users: get all requests, respect employee_id filter if provided
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $filterData['company_id'] = $effectiveCompanyId;
            // employee_id is already in filterData from request if provided
        } else {
            $subordinateIds = $this->getSubordinateEmployeeIds($user);

            if (!empty($subordinateIds)) {
                $subordinateIds[] = $user->user_id;

                // If employee_id is provided, verify it's in subordinates
                if (isset($filterData['employee_id']) && $filterData['employee_id'] !== null) {
                    $requestedEmployeeId = (int) $filterData['employee_id'];
                    if (!in_array($requestedEmployeeId, $subordinateIds, true)) {
                        // Requested employee not in subordinates - use impossible ID
                        $filterData['employee_id'] = -1;
                        $filterData['employee_ids'] = null;
                    }
                    // else keep employee_id from request
                } else {
                    // No specific employee requested, show all subordinates
                    $filterData['employee_ids'] = $subordinateIds;
                }
                $filterData['company_id'] = $user->company_id;
            } else {
                // No subordinates, only own requests
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
            Log::info('ResignationService::getResignationById - Invalid arguments', [
                'id' => $id,
                'message' => 'Invalid arguments'
            ]);
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
                    Log::warning('ResignationService::getResignationById - Hierarchy permission denied', [
                        'request_id' => $id,
                        'requester_id' => $user->user_id,
                        'requester_type' => $user->user_type,
                        'employee_id' => $resignation->employee_id,
                        'requester_level' => $this->permissionService->getUserHierarchyLevel($user),
                        'employee_level' => $this->permissionService->getUserHierarchyLevel($employee),
                        'requester_department' => $this->permissionService->getUserDepartmentId($user),
                        'employee_department' => $this->permissionService->getUserDepartmentId($employee),
                        'message' => 'Hierarchy permission denied'
                    ]);
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

            $resignation = $this->resignationRepository->createResignation($dto);

            if (!$resignation) {
                Log::warning('ResignationService::createResignation - Failed to create resignation', [
                    'employee_id' => $dto->employeeId,
                    'message' => 'فشل في إنشاء طلب الاستقالة'
                ]);
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

            // Send email notification
            $employee = User::find($dto->employeeId);
            if ($employee && $employee->email) {
                SendEmailNotificationJob::dispatch(
                    new ResignationSubmitted(
                        employeeName: $employee->full_name ?? $employee->first_name,
                        resignationDate: $resignation->resignation_date ?? now()->format('Y-m-d'),
                        lastWorkingDay: $resignation->last_working_day ?? 'غير محدد',
                        reason: $dto->reason ?? null
                    ),
                    $employee->email
                );
            }

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
                Log::warning('ResignationService::updateResignation - Resignation not found', [
                    'id' => $id,
                    'message' => 'طلب '
                ]);
                throw new \Exception('طلب الاستقالة غير موجود');
            }

            if ($resignation->status !== Resignation::STATUS_PENDING) {
                Log::warning('ResignationService::updateResignation - Resignation is not pending', [
                    'id' => $id,
                    'message' => 'Resignation is not pending'
                ]);
                throw new \Exception('لا يمكن تعديل طلب الاستقالة بعد معالجته');
            }

            // صلاحية التعديل: صاحب الطلب، مدير الشركة، أو من لديه صلاحية رؤية طلبات الموظف
            $isOwner = $resignation->employee_id === $user->user_id;
            $isCompany = $user->user_type === 'company';

            if (!$isOwner && !$isCompany) {
                $employee = User::find($resignation->employee_id);
                if (!$employee || !$this->permissionService->canApproveEmployeeRequests($user, $employee)) {
                    Log::warning('ResignationService::updateResignation - Permission denied', [
                        'resignation_id' => $id,
                        'message' => 'ليس لديك صلاحية لتعديل هذا الطلب',
                        'user_id' => $user->user_id
                    ]);
                    throw new \Exception('ليس لديك صلاحية لتعديل هذا الطلب');
                }
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

                Log::warning('ResignationService::deleteResignation - Resignation not found', [
                    'id' => $id,
                    'message' => 'Resignation not found'
                ]);
                throw new \Exception('طلب الاستقالة غير موجود');
            }

            // 1. Status Check: Only Pending can be deleted
            if ($resignation->status !== Resignation::STATUS_PENDING) {
                Log::info('ResignationService::deleteResignation - Resignation not pending', [
                    'resignation_id' => $id,
                    'message' => 'لا يمكن حذف طلب الاستقالة بعد معالجته',
                    'deleted_by' => $user->user_id,
                ]);
                throw new \Exception('لا يمكن حذف طلب الاستقالة بعد معالجته');
            }

            // 2. Permission Check
            $isOwner = $resignation->employee_id === $user->user_id;
            $isCompany = $user->user_type === 'company';

            // Check hierarchy permission (is a manager of the employee)
            $isHierarchyManager = false;
            if (!$isOwner && !$isCompany) {
                $employee = User::find($resignation->employee_id);
                if ($employee && $this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    $isHierarchyManager = true;
                }
            }

            if (!$isOwner && !$isCompany && !$isHierarchyManager) {
                Log::info('ResignationService::deleteResignation - Permission denied', [
                    'resignation_id' => $id,
                    'message' => 'ليس لديك صلاحية لحذف هذا الطلب',
                    'deleted_by' => $user->user_id,
                ]);
                throw new \Exception('ليس لديك صلاحية لحذف هذا الطلب');
            }

            // Determine cancel reason based on who is cancelling
            $cancelReason = $isOwner
                ? 'تم إلغاء الطلب من قبل الموظف'
                : 'تم إلغاء الطلب من قبل الإدارة';

            $this->resignationRepository->rejectResignation($resignation, $user->user_id, $cancelReason);
            return true;
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
                Log::info('ResignationService::approveOrRejectResignation - Resignation not found', [
                    'resignation_id' => $id,
                    'message' => 'طلب الاستقالة غير موجود',
                    'approved_by' => $user->user_id,
                ]);
                throw new \Exception('طلب الاستقالة غير موجود');
            }

            if ($resignation->status !== Resignation::STATUS_PENDING) {
                Log::info('ResignationService::approveOrRejectResignation - Resignation not pending', [
                    'resignation_id' => $id,
                    'message' => 'تم معالجة طلب الاستقالة مسبقاً',
                    'approved_by' => $user->user_id,
                ]);
                throw new \Exception('تم معالجة طلب الاستقالة مسبقاً');
            }

            // التحقق من صلاحيات الموافقة (strict: must be higher level)
            if ($user->user_type !== 'company') {
                $employee = User::find($resignation->employee_id);
                if (!$employee || !$this->permissionService->canApproveEmployeeRequests($user, $employee)) {
                    Log::info('ResignationService::approveOrRejectResignation - Permission denied', [
                        'resignation_id' => $id,
                        'message' => 'ليس لديك صلاحية لمعالجة طلب استقالة هذا الموظف',
                        'approved_by' => $user->user_id,
                    ]);
                    throw new \Exception('ليس لديك صلاحية لمعالجة طلب استقالة هذا الموظف');
                }
            }

            $userType = strtolower(trim($user->user_type ?? ''));

            // Company user can approve/reject directly
            if ($userType === 'company') {
                if ($dto->action === 'approve') {
                    $processedResignation = $this->resignationRepository->approveResignation(
                        $resignation,
                        $dto->processedBy,
                        $dto->remarks
                    );

                    // Record final approval
                    $this->approvalService->recordApproval(
                        $processedResignation->resignation_id,
                        $dto->processedBy,
                        1, // approved
                        1, // final level
                        'resignation_settings',
                        $effectiveCompanyId
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

                    // Send approval email
                    $employee = User::find($processedResignation->employee_id);
                    if ($employee && $employee->email) {
                        SendEmailNotificationJob::dispatch(
                            new ResignationApproved(
                                employeeName: $employee->full_name ?? $employee->first_name,
                                resignationDate: $processedResignation->resignation_date ?? now()->format('Y-m-d'),
                                lastWorkingDay: $processedResignation->last_working_day ?? 'غير محدد',
                                remarks: $dto->remarks
                            ),
                            $employee->email
                        );
                    }

                    return $processedResignation;
                } else {
                    // Company rejection
                    $processedResignation = $this->resignationRepository->rejectResignation(
                        $resignation,
                        $dto->processedBy,
                        $dto->remarks
                    );

                    // Record rejection
                    $this->approvalService->recordApproval(
                        $processedResignation->resignation_id,
                        $dto->processedBy,
                        2, // rejected
                        2, // rejection level
                        'resignation_settings',
                        $effectiveCompanyId
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

                    // Send rejection email
                    $employee = User::find($processedResignation->employee_id);
                    if ($employee && $employee->email) {
                        SendEmailNotificationJob::dispatch(
                            new ResignationRejected(
                                employeeName: $employee->full_name ?? $employee->first_name,
                                resignationDate: $processedResignation->resignation_date ?? now()->format('Y-m-d'),
                                lastWorkingDay: $processedResignation->last_working_day ?? 'غير محدد',
                                reason: $dto->remarks
                            ),
                            $employee->email
                        );
                    }

                    return $processedResignation;
                }
            }

            // For staff users, verify approval levels
            $canApprove = $this->approvalService->canUserApprove(
                $user->user_id,
                $resignation->resignation_id,
                $resignation->employee_id,
                'resignation_settings'
            );

            if (!$canApprove) {
                throw new \Exception('ليس لديك صلاحية للموافقة على هذا الطلب في المرحلة الحالية');
            }

            if ($dto->action === 'approve') {
                // Check if this is the final approval
                $isFinal = $this->approvalService->isFinalApproval(
                    $resignation->resignation_id,
                    $resignation->employee_id,
                    'resignation_settings'
                );

                if ($isFinal) {
                    // Final approval
                    $processedResignation = $this->resignationRepository->approveResignation(
                        $resignation,
                        $dto->processedBy,
                        $dto->remarks
                    );

                    // Record final approval
                    $this->approvalService->recordApproval(
                        $processedResignation->resignation_id,
                        $dto->processedBy,
                        1,
                        1,
                        'resignation_settings',
                        $effectiveCompanyId
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

                    // Send approval email
                    $employee = User::find($processedResignation->employee_id);
                    if ($employee && $employee->email) {
                        SendEmailNotificationJob::dispatch(
                            new ResignationApproved(
                                employeeName: $employee->full_name ?? $employee->first_name,
                                resignationDate: $processedResignation->resignation_date ?? now()->format('Y-m-d'),
                                lastWorkingDay: $processedResignation->last_working_day ?? 'غير محدد',
                                remarks: $dto->remarks
                            ),
                            $employee->email
                        );
                    }

                    return $processedResignation;
                } else {
                    // Intermediate approval - just record it, don't change status
                    $this->approvalService->recordApproval(
                        $resignation->resignation_id,
                        $dto->processedBy,
                        1, // approved
                        0, // intermediate level
                        'resignation_settings',
                        $effectiveCompanyId
                    );

                    // Send intermediate approval notification
                    $this->notificationService->sendApprovalNotification(
                        'resignation_settings',
                        (string)$resignation->resignation_id,
                        $effectiveCompanyId,
                        StringStatusEnum::APPROVED->value,
                        $dto->processedBy,
                        1,
                        $resignation->employee_id
                    );

                    // Reload to get updated approvals
                    $resignation->refresh();

                    return $resignation;
                }
            } else {
                $processedResignation = $this->resignationRepository->rejectResignation(
                    $resignation,
                    $dto->processedBy,
                    $dto->remarks
                );

                // Record rejection
                $this->approvalService->recordApproval(
                    $processedResignation->resignation_id,
                    $dto->processedBy,
                    2, // rejected
                    2, // rejection level
                    'resignation_settings',
                    $effectiveCompanyId
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

                // Send rejection email
                $employee = User::find($processedResignation->employee_id);
                if ($employee && $employee->email) {
                    SendEmailNotificationJob::dispatch(
                        new ResignationRejected(
                            employeeName: $employee->full_name ?? $employee->first_name,
                            resignationDate: $processedResignation->resignation_date ?? now()->format('Y-m-d'),
                            lastWorkingDay: $processedResignation->last_working_day ?? 'غير محدد',
                            reason: $dto->remarks
                        ),
                        $employee->email
                    );
                }

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
