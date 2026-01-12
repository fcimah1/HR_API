<?php

namespace App\Services;

use App\DTOs\Transfer\CreateTransferDTO;
use App\DTOs\Transfer\TransferFilterDTO;
use App\DTOs\Transfer\ApproveRejectTransferDTO;
use App\DTOs\Transfer\UpdateTransferDTO;
use App\DTOs\Transfer\CompanyApprovalDTO;
use App\DTOs\Transfer\ExecuteTransferDTO;
use App\DTOs\Transfer\GetBranchesDTO;
use App\Models\Transfer;
use App\Models\User;
use App\Repository\Interface\TransferRepositoryInterface;
use App\Services\SimplePermissionService;
use App\Services\NotificationService;
use App\Services\ApprovalService;
use App\Enums\StringStatusEnum;
use App\Enums\NumericalStatusEnum;
use App\Enums\TransferTypeEnum;
use App\Jobs\SendEmailNotificationJob;
use App\Mail\Transfer\TransferSubmitted;
use App\Mail\Transfer\TransferApproved;
use App\Mail\Transfer\TransferRejected;
use App\Repository\Interface\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class TransferService
{
    public function __construct(
        protected TransferRepositoryInterface $transferRepository,
        protected SimplePermissionService $permissionService,
        protected NotificationService $notificationService,
        protected CacheService $cacheService,
        protected UserRepositoryInterface $userRepository,
        protected ApprovalService $approvalService,
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
            // الحفاظ على employee_id من الـ request إذا كان موجود
            // (employee_id محفوظ بالفعل من $filters->toArray())
        } else {

            $subordinateIds = $this->userRepository->getSubordinateEmployeeIds($user);

            if (!empty($subordinateIds)) {
                // لديه موظفين تابعين: طلباته + طلبات التابعين

                // Filter subordinates based on restrictions (Department/Branch restrictions from OperationRestriction)
                $subordinateIds = array_filter($subordinateIds, function ($empId) use ($user) {
                    $emp = User::find($empId);
                    if (!$emp) return false;
                    return $this->permissionService->canViewEmployeeRequests($user, $emp);
                });

                $subordinateIds[] = $user->user_id; // إضافة نفسه
                $filterData['employee_ids'] = $subordinateIds;
                $filterData['company_id'] = $user->company_id;

                // إضافة فلترة المستويات الهرمية للموظفين التابعين
                $hierarchyLevels = $this->permissionService->getUserHierarchyLevel($user);
                if ($hierarchyLevels !== null) {
                    // جلب المستويات الأعلى من مستوى المدير
                    $filterData['hierarchy_levels'] = range($hierarchyLevels + 1, 5);
                }
            } else {
                // ليس لديه موظفين تابعين: طلباته فقط
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
            Log::error('TransferService::getTransferById - Invalid arguments', [
                'transfer_id' => $id,
                'message' => 'يجب توفير معرف الشركة أو معرف المستخدم',
                'user_id' => $user->user_id,
            ]);
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
                    Log::warning('TransferService::getTransferById - Permission denied', [
                        'transfer_id' => $id,
                        'message' => 'غير مسموح بعرض هذا النقل',
                        'user_id' => $user->user_id,
                    ]);
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
     * الحصول على الشركات مع الفروع للنقل بين الشركات
     */
    public function getCompaniesWithBranches(): array
    {
        return $this->transferRepository->getCompaniesWithBranches();
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

            // التحقق من وجود طلب نقل معلق للموظف
            $this->checkForExistingPendingTransfer($dto->employeeId);

            // التحقق من صلاحية المستخدم لطلب النقل لهذا الموظف (IDOR Protection)
            $currentUser = Auth::user();
            if ($currentUser && $currentUser->user_type !== 'company') { // Company owner can always transfer
                $targetEmployee = User::find($dto->employeeId);
                // If target is self, usually allowed, but check canViewEmployeeRequests logic just in case (it handles self if needed or we skip)
                // Actually manager requesting for self is valid? Usually yes.
                // canViewEmployeeRequests returns false if manager=level 4 and employee=level 4 (peers).
                // But users can often request for themselves.
                if ($targetEmployee && $currentUser->user_id !== $targetEmployee->user_id) {
                    if (!$this->permissionService->canViewEmployeeRequests($currentUser, $targetEmployee)) {
                        Log::warning('TransferService::createTransfer - Unauthorized attempt', [
                            'user_id' => $currentUser->user_id,
                            'target_employee_id' => $dto->employeeId,
                        ]);
                        throw new \Illuminate\Auth\Access\AuthorizationException('ليس لديك صلاحية لطلب النقل لهذا الموظف');
                    }
                }
            }

            // تعبئة البيانات القديمة من بيانات الموظف الحالية
            $populateEmployeeData = $this->populateEmployeeData($dto);

            $transfer = $this->transferRepository->createTransfer($populateEmployeeData);

            if (!$transfer) {
                Log::warning('TransferService::createTransfer - Failed to create transfer', [
                    'employee_id' => $dto->employeeId,
                    'message' => 'فشل في إنشاء طلب النقل',
                ]);
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

            // إرسال إشعار للمستلم المحدد (إذا وجد)
            if ($dto->notifySendTo) {
                $this->notificationService->sendCustomNotification(
                    'transfer_settings',
                    (string)$transfer->transfer_id,
                    $dto->notifySendTo,
                    NumericalStatusEnum::PENDING->value
                );
            }

            // Send email notification
            $employee = User::find($dto->employeeId);
            if ($employee && $employee->email) {
                $fromDept = $transfer->from_department_name ?? 'غير محدد';
                $toDept = $transfer->to_department_name ?? 'غير محدد';
                $transferType = $transfer->transfer_type ?? 'نقل';

                SendEmailNotificationJob::dispatch(
                    new TransferSubmitted(
                        employeeName: $employee->full_name ?? $employee->first_name,
                        transferType: $transferType,
                        fromDepartment: $fromDept,
                        toDepartment: $toDept,
                        reason: $dto->reason
                    ),
                    $employee->email
                );
            }

            return $transfer;
        });
    }

    /**
     * تعبئة بيانات الموظف القديمة تلقائياً
     */
    private function populateEmployeeData(CreateTransferDTO $dto): CreateTransferDTO
    {
        $employee = User::find($dto->employeeId);
        $employeeDetails = \App\Models\UserDetails::where('user_id', $dto->employeeId)->first();

        if (!$employee || !$employeeDetails) {
            return $dto;
        }

        // إنشاء DTO جديد مع البيانات القديمة المحدثة
        return new CreateTransferDTO(
            companyId: $dto->companyId,
            employeeId: $dto->employeeId,
            addedBy: $dto->addedBy,
            transferDate: $dto->transferDate,
            transferDepartment: $dto->transferDepartment,
            transferDesignation: $dto->transferDesignation,
            reason: $dto->reason,
            oldSalary: $dto->oldSalary ?? (int) $employeeDetails->basic_salary,
            oldDesignation: $dto->oldDesignation ?? $employeeDetails->designation_id,
            oldDepartment: $dto->oldDepartment ?? $employeeDetails->department_id,
            newSalary: $dto->newSalary,
            oldCompanyId: $dto->oldCompanyId ?? $employee->company_id,
            oldBranchId: $dto->oldBranchId ?? $employeeDetails->branch_id,
            newCompanyId: $dto->newCompanyId,
            newBranchId: $dto->newBranchId,
            oldCurrency: $dto->oldCurrency ?? $employeeDetails->currency_id,
            newCurrency: $dto->newCurrency,
            transferType: $dto->transferType,
            currentCompanyApproval: $dto->currentCompanyApproval,
            newCompanyApproval: $dto->newCompanyApproval,
            status: $dto->status,
        );
    }

    /**
     * التحقق من وجود طلب نقل معلق للموظف
     */
    private function checkForExistingPendingTransfer(int $employeeId): void
    {
        $existingTransfer = $this->transferRepository->findPendingTransferForEmployee($employeeId);

        if ($existingTransfer) {
            Log::warning('TransferService::checkForExistingPendingTransfer - Existing pending transfer found', [
                'employee_id' => $employeeId,
                'existing_transfer_id' => $existingTransfer->transfer_id,
                'transfer_date' => $existingTransfer->transfer_date,
                'message' => 'يوجد طلب نقل معلق لهذا الموظف بتاريخ ' . $existingTransfer->transfer_date .
                    '. يرجى إلغائه أو انتظار معالجته قبل إنشاء طلب جديد.'
            ]);

            throw new \Exception(
                'يوجد طلب نقل معلق لهذا الموظف بتاريخ ' . $existingTransfer->transfer_date .
                    '. يرجى إلغائه أو انتظار معالجته قبل إنشاء طلب جديد.'
            );
        }
    }

    /**
     * تحديث نقل
     */
    public function updateTransfer(int $id, UpdateTransferDTO $dto, User $user, ?string $expectedType = null): Transfer
    {
        return DB::transaction(function () use ($id, $dto, $user, $expectedType) {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $transfer = $this->transferRepository->findTransferById($id, $effectiveCompanyId);

            if (!$transfer) {
                Log::warning('TransferService::updateTransfer - Transfer not found', [
                    'transfer_id' => $id,
                    'message' => 'طلب النقل غير موجود',
                    'user_id' => $user->user_id,
                ]);
                throw new \Exception('طلب النقل غير موجود');
            }

            // Check for expected type mismatch
            if ($expectedType !== null && $transfer->transfer_type !== $expectedType) {
                Log::warning('TransferService::updateTransfer - Type mismatch', [
                    'transfer_id' => $id,
                    'expected_type' => $expectedType,
                    'actual_type' => $transfer->transfer_type,
                    'user_id' => $user->user_id,
                ]);
                throw new \Exception("لا يمكنك تعديل طلب من نوع ({$transfer->transfer_type_text}) من خلال هذا الرابط.");
            }

            if ($transfer->status !== Transfer::STATUS_PENDING) {
                Log::warning('TransferService::updateTransfer - Transfer not pending', [
                    'transfer_id' => $id,
                    'message' => 'لا يمكن تعديل طلب النقل بعد معالجته',
                    'user_id' => $user->user_id,
                ]);
                throw new \Exception('لا يمكن تعديل طلب النقل بعد معالجته');
            }

            // صلاحية التعديل: صاحب الطلب، مدير الشركة، أو من لديه صلاحية رؤية طلبات الموظف
            $isOwner = $transfer->employee_id === $user->user_id;
            $isCompany = $user->user_type === 'company';

            if (!$isOwner && !$isCompany) {
                $employee = User::find($transfer->employee_id);
                if (!$employee || !$this->permissionService->canApproveEmployeeRequests($user, $employee)) {
                    Log::warning('TransferService::updateTransfer - Permission denied', [
                        'transfer_id' => $id,
                        'message' => 'ليس لديك صلاحية لتعديل هذا الطلب',
                        'user_id' => $user->user_id,
                    ]);
                    throw new \Exception('ليس لديك صلاحية لتعديل هذا الطلب');
                }
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
                Log::warning('TransferService::deleteTransfer - Transfer not found', [
                    'transfer_id' => $id,
                    'message' => 'طلب النقل غير موجود',
                    'user_id' => $user->user_id,
                ]);
                throw new \Exception('طلب النقل غير موجود');
            }

            // 1. Status Check: Only Pending can be deleted
            if ($transfer->status !== Transfer::STATUS_PENDING) {
                Log::info('TransferService::deleteTransfer - Transfer not pending', [
                    'transfer_id' => $id,
                    'message' => 'لا يمكن إلغاء طلب النقل بعد معالجته',
                    'deleted_by' => $user->user_id,
                ]);
                throw new \Exception('لا يمكن إلغاء طلب النقل بعد معالجته');
            }

            // 2. Permission Check
            $isAdder = $transfer->added_by === $user->user_id;
            $isCompany = $user->user_type === 'company';

            // Check hierarchy permission (is a manager of the employee being transferred)
            $isHierarchyManager = false;
            if (!$isAdder && !$isCompany) {
                $employee = User::find($transfer->employee_id);
                if ($employee && $this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    $isHierarchyManager = true;
                }
            }

            if (!$isAdder && !$isCompany && !$isHierarchyManager) {
                Log::info('TransferService::deleteTransfer - Permission denied', [
                    'transfer_id' => $id,
                    'message' => 'ليس لديك صلاحية لإلغاء هذا الطلب',
                    'deleted_by' => $user->user_id,
                ]);
                throw new \Exception('ليس لديك صلاحية لإلغاء هذا الطلب');
            }

            // Determine cancel reason based on who is cancelling
            $cancelReason = $isAdder
                ? 'تم إلغاء الطلب من قبل الموظف'
                : 'تم إلغاء الطلب من قبل الإدارة';

            $this->transferRepository->rejectTransfer($transfer, $user->user_id, $cancelReason);
            return true;
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
                Log::info('TransferService::approveOrRejectTransfer - Transfer not found', [
                    'transfer_id' => $id,
                    'message' => 'طلب النقل غير موجود',
                    'approved_by' => $user->user_id,
                ]);
                throw new \Exception('طلب النقل غير موجود');
            }

            if ($transfer->status !== Transfer::STATUS_PENDING) {
                Log::info('TransferService::approveOrRejectTransfer - Transfer not pending', [
                    'transfer_id' => $id,
                    'message' => 'تم معالجة طلب النقل مسبقاً',
                    'approved_by' => $user->user_id,
                ]);
                throw new \Exception('تم معالجة طلب النقل مسبقاً');
            }

            // التحقق من صلاحيات الموافقة (strict: must be higher level)
            if ($user->user_type !== 'company') {
                $employee = User::find($transfer->employee_id);
                if (!$employee || !$this->permissionService->canApproveEmployeeRequests($user, $employee)) {
                    Log::info('TransferService::approveOrRejectTransfer - Permission denied', [
                        'transfer_id' => $id,
                        'message' => 'ليس لديك صلاحية لمعالجة طلب نقل هذا الموظف',
                        'approved_by' => $user->user_id,
                    ]);
                    throw new \Exception('ليس لديك صلاحية لمعالجة طلب نقل هذا الموظف');
                }
            }

            $userType = strtolower(trim($user->user_type ?? ''));

            // Company user can approve directly
            if ($userType === 'company') {
                if ($dto->action === 'approve') {
                    $processedTransfer = $this->transferRepository->approveTransfer(
                        $transfer,
                        $dto->processedBy,
                        $dto->remarks
                    );

                    // Record final approval
                    $this->approvalService->recordApproval(
                        $processedTransfer->transfer_id,
                        $dto->processedBy,
                        1, // approved
                        1, // final level
                        'transfer_settings',
                        $effectiveCompanyId,
                        $processedTransfer->employee_id
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

                    // Send approval email
                    $employee = User::find($processedTransfer->employee_id);
                    if ($employee && $employee->email) {
                        SendEmailNotificationJob::dispatch(
                            new TransferApproved(
                                employeeName: $employee->full_name ?? $employee->first_name,
                                transferType: $processedTransfer->transfer_type ?? 'نقل',
                                fromDepartment: $processedTransfer->from_department_name ?? 'غير محدد',
                                toDepartment: $processedTransfer->to_department_name ?? 'غير محدد',
                                remarks: $dto->remarks
                            ),
                            $employee->email
                        );
                    }

                    return $processedTransfer;
                }
            }

            // For staff users, verify approval levels
            $canApprove = $this->approvalService->canUserApprove(
                $user->user_id,
                $transfer->transfer_id,
                $transfer->employee_id,
                'transfer_settings'
            );

            if (!$canApprove) {
                $denialInfo = $this->approvalService->getApprovalDenialReason(
                    $user->user_id,
                    $transfer->transfer_id,
                    $transfer->employee_id,
                    'transfer_settings'
                );
                Log::warning('TransferService::approveOrRejectTransfer - Approval denied', [
                    'transfer_id' => $id,
                    'message' => $denialInfo['message'],
                    'approved_by' => $user->user_id,
                ]);
                throw new \Exception($denialInfo['message']);
            }

            if ($dto->action === 'approve') {
                // Check if this is the final approval
                $isFinal = $this->approvalService->isFinalApproval(
                    $transfer->transfer_id,
                    $transfer->employee_id,
                    'transfer_settings'
                );

                if ($isFinal) {
                    // Final approval - update request status
                    $processedTransfer = $this->transferRepository->approveTransfer(
                        $transfer,
                        $dto->processedBy,
                        $dto->remarks
                    );

                    // Record final approval
                    $this->approvalService->recordApproval(
                        $processedTransfer->transfer_id,
                        $dto->processedBy,
                        1, // approved
                        1, // final level
                        'transfer_settings',
                        $effectiveCompanyId,
                        $processedTransfer->employee_id
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

                    // Send approval email
                    $employee = User::find($processedTransfer->employee_id);
                    if ($employee && $employee->email) {
                        SendEmailNotificationJob::dispatch(
                            new TransferApproved(
                                employeeName: $employee->full_name ?? $employee->first_name,
                                transferType: $processedTransfer->transfer_type ?? 'نقل',
                                fromDepartment: $processedTransfer->from_department_name ?? 'غير محدد',
                                toDepartment: $processedTransfer->to_department_name ?? 'غير محدد',
                                remarks: $dto->remarks
                            ),
                            $employee->email
                        );
                    }

                    return $processedTransfer;
                } else {
                    // Intermediate approval - just record it, don't change status
                    $this->approvalService->recordApproval(
                        $transfer->transfer_id,
                        $dto->processedBy,
                        1, // approved
                        0, // intermediate level
                        'transfer_settings',
                        $effectiveCompanyId,
                        $transfer->employee_id
                    );

                    // Send intermediate approval notification
                    $this->notificationService->sendApprovalNotification(
                        'transfer_settings',
                        (string)$transfer->transfer_id,
                        $effectiveCompanyId,
                        StringStatusEnum::APPROVED->value,
                        $dto->processedBy,
                        1,
                        $transfer->employee_id
                    );

                    // Reload to get updated approvals
                    $transfer->refresh();
                    $transfer->load(['employee', 'approvals.staff']);

                    return $transfer;
                }
            } else {
                $processedTransfer = $this->transferRepository->rejectTransfer(
                    $transfer,
                    $dto->processedBy,
                    $dto->remarks
                );

                // Record rejection
                $this->approvalService->recordApproval(
                    $processedTransfer->transfer_id,
                    $dto->processedBy,
                    2, // rejected
                    2, // rejection level
                    'transfer_settings',
                    $effectiveCompanyId,
                    $processedTransfer->employee_id
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

                // Send rejection email
                $employee = User::find($processedTransfer->employee_id);
                if ($employee && $employee->email) {
                    SendEmailNotificationJob::dispatch(
                        new TransferRejected(
                            employeeName: $employee->full_name ?? $employee->first_name,
                            transferType: $processedTransfer->transfer_type ?? 'نقل',
                            fromDepartment: $processedTransfer->from_department_name ?? 'غير محدد',
                            toDepartment: $processedTransfer->to_department_name ?? 'غير محدد',
                            reason: $dto->remarks
                        ),
                        $employee->email
                    );
                }

                return $processedTransfer;
            }
        });
    }

    /**
     * موافقة الشركة الحالية على النقل بين الشركات
     */
    public function approveByCurrentCompany(int $id, CompanyApprovalDTO $dto): Transfer
    {
        return DB::transaction(function () use ($id, $dto) {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $transfer = $this->transferRepository->findTransferById($id, $effectiveCompanyId);

            if (!$transfer) {
                Log::info('TransferService::approveByCurrentCompany - Transfer not found', [
                    'transfer_id' => $id,
                    'message' => 'طلب النقل غير موجود',
                    'approved_by' => $user->user_id,
                ]);
                throw new \Exception('طلب النقل غير موجود');
            }

            if ($transfer->transfer_type !== Transfer::TYPE_INTERCOMPANY) {
                Log::info('TransferService::approveByCurrentCompany - Transfer not intercompany', [
                    'transfer_id' => $id,
                    'message' => 'هذا الطلب ليس نقل بين شركات',
                    'approved_by' => $user->user_id,
                ]);
                throw new \Exception('هذا الطلب ليس نقل بين شركات');
            }

            if ($transfer->current_company_approval !== Transfer::APPROVAL_PENDING) {
                Log::info('TransferService::approveByCurrentCompany - Transfer not pending', [
                    'transfer_id' => $id,
                    'message' => 'تمت معالجة موافقة الشركة الحالية مسبقاً',
                    'approved_by' => $user->user_id,
                ]);
                throw new \Exception('تمت معالجة موافقة الشركة الحالية مسبقاً');
            }

            // التحقق من أن المستخدم من الشركة الحالية
            if ($effectiveCompanyId !== $transfer->old_company_id) {
                Log::info('TransferService::approveByCurrentCompany - Permission denied', [
                    'transfer_id' => $id,
                    'message' => 'ليس لديك صلاحية - يجب أن تكون من الشركة الحالية',
                    'approved_by' => $user->user_id,
                ]);
                throw new \Exception('ليس لديك صلاحية - يجب أن تكون من الشركة الحالية');
            }

            if ($dto->isApprove()) {
                $transfer->update([
                    'current_company_approval' => Transfer::APPROVAL_APPROVED,
                ]);

                $this->notificationService->sendApprovalNotification(
                    'transfer_settings',
                    (string)$transfer->transfer_id,
                    $effectiveCompanyId,
                    'current_company_approved',
                    $dto->approvedBy,
                    null, // approval level
                    $transfer->employee_id
                );
            } else {
                $transfer->update([
                    'current_company_approval' => Transfer::APPROVAL_REJECTED,
                    'status' => Transfer::STATUS_REJECTED,
                ]);

                $this->notificationService->sendApprovalNotification(
                    'transfer_settings',
                    (string)$transfer->transfer_id,
                    $effectiveCompanyId,
                    StringStatusEnum::REJECTED->value,
                    $dto->approvedBy,
                    null, // approval level
                    $transfer->employee_id
                );
            }

            return $transfer->fresh();
        });
    }

    /**
     * موافقة الشركة الجديدة على النقل بين الشركات
     */
    public function approveByNewCompany(int $id, CompanyApprovalDTO $dto): Transfer
    {
        return DB::transaction(function () use ($id, $dto) {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // البحث في الشركة الجديدة
            $transfer = Transfer::where('transfer_id', $id)
                ->where('new_company_id', $effectiveCompanyId)
                ->first();

            if (!$transfer) {
                Log::info('TransferService::approveByNewCompany - Transfer not found', [
                    'transfer_id' => $id,
                    'message' => 'طلب النقل غير موجود',
                    'approved_by' => $user->user_id,
                ]);
                throw new \Exception('طلب النقل غير موجود');
            }

            if ($transfer->transfer_type !== Transfer::TYPE_INTERCOMPANY) {
                Log::info('TransferService::approveByNewCompany - Transfer not intercompany', [
                    'transfer_id' => $id,
                    'message' => 'هذا الطلب ليس نقل بين شركات',
                    'approved_by' => $user->user_id,
                ]);
                throw new \Exception('هذا الطلب ليس نقل بين شركات');
            }

            if ($transfer->current_company_approval !== Transfer::APPROVAL_APPROVED) {
                Log::info('TransferService::approveByNewCompany - Current company approval not approved', [
                    'transfer_id' => $id,
                    'message' => 'يجب موافقة الشركة الحالية أولاً',
                    'approved_by' => $user->user_id,
                ]);
                throw new \Exception('يجب موافقة الشركة الحالية أولاً');
            }

            if ($transfer->new_company_approval !== Transfer::APPROVAL_PENDING) {
                Log::info('TransferService::approveByNewCompany - New company approval not pending', [
                    'transfer_id' => $id,
                    'message' => 'تمت معالجة موافقة الشركة الجديدة مسبقاً',
                    'approved_by' => $user->user_id,
                ]);
                throw new \Exception('تمت معالجة موافقة الشركة الجديدة مسبقاً');
            }

            if ($dto->isApprove()) {
                // التحقق من المتطلبات قبل الموافقة النهائية
                $validation = $this->validatePreTransferRequirements($transfer->employee_id);

                if (!$validation['can_transfer']) {
                    // حفظ أسباب المنع
                    $blockedReasons = [];
                    $blockerDetails = [];

                    if (!$validation['validations']['active_leaves']['passed']) {
                        $blockedReasons['active_leaves'] = true;
                        $blockerDetails['active_leaves'] = [
                            'message' => 'لديه إجازات نشطة',
                            'count' => $validation['validations']['active_leaves']['count'],
                        ];
                    }
                    if (!$validation['validations']['active_advances']['passed']) {
                        $blockedReasons['active_advances'] = true;
                        $blockerDetails['active_advances'] = [
                            'message' => 'لديه سلف غير مسددة',
                            'count' => $validation['validations']['active_advances']['count'],
                        ];
                    }
                    if (!$validation['validations']['unreturned_custody']['passed']) {
                        $blockedReasons['unreturned_custody'] = true;
                        $blockerDetails['unreturned_custody'] = [
                            'message' => 'لديه عهد غير مرتجعة',
                            'count' => $validation['validations']['unreturned_custody']['count'],
                        ];
                    }

                    $transfer->update([
                        'validation_notes' => json_encode($validation['validations']),
                        'blocked_reasons' => json_encode($blockedReasons),
                    ]);

                    // رمي exception مع التفاصيل
                    throw new \Exception(json_encode([
                        'message' => 'لا يمكن الموافقة - الموظف لديه متطلبات غير مستوفاة',
                        'blockers' => $blockerDetails,
                        'validations' => $validation['validations'],
                    ]));
                }

                // إنشاء DTO للتنفيذ
                $executeDTO = new ExecuteTransferDTO(
                    executedBy: $dto->approvedBy,
                    forceCustodyClearance: false,
                    notes: $dto->remarks
                );

                $transfer->update([
                    'new_company_approval' => Transfer::APPROVAL_APPROVED,
                    'status' => Transfer::STATUS_APPROVED,
                    'executed_at' => now(),
                    'executed_by' => $executeDTO->executedBy,
                    'validation_notes' => $executeDTO->notes,
                ]);

                // تنفيذ النقل - تحديث بيانات الموظف
                $this->executeTransfer($transfer);

                $this->notificationService->sendApprovalNotification(
                    'transfer_settings',
                    (string)$transfer->transfer_id,
                    $effectiveCompanyId,
                    StringStatusEnum::APPROVED->value,
                    $dto->approvedBy,
                    null, // approval level
                    $transfer->employee_id
                );
            } else {
                $transfer->update([
                    'new_company_approval' => Transfer::APPROVAL_REJECTED,
                    'status' => Transfer::STATUS_REJECTED,
                ]);

                $this->notificationService->sendApprovalNotification(
                    'transfer_settings',
                    (string)$transfer->transfer_id,
                    $effectiveCompanyId,
                    StringStatusEnum::REJECTED->value,
                    $dto->approvedBy,
                    null, // approval level
                    $transfer->employee_id
                );
            }

            return $transfer->fresh();
        });
    }

    /**
     * الحصول على حالات النقل
     */
    public function getTransferStatuses(): array
    {

        return [
            'cases' => NumericalStatusEnum::toArray(),
            'transfer_types' => TransferTypeEnum::toArray(),
        ];
    }

    /**
     * التحقق من المتطلبات قبل النقل (للنقل بين الشركات)
     */
    public function validatePreTransferRequirements(int $employeeId): array
    {
        $activeLeaves = $this->transferRepository->getActiveLeaves($employeeId);
        $activeAdvances = $this->transferRepository->getActiveAdvances($employeeId);
        $unreturnedCustody = $this->transferRepository->getUnreturnedCustody($employeeId);

        return [
            'can_transfer' => empty($activeLeaves) && empty($activeAdvances) && empty($unreturnedCustody),
            'validations' => [
                'active_leaves' => [
                    'passed' => empty($activeLeaves),
                    'count' => count($activeLeaves),
                    'items' => $activeLeaves,
                ],
                'active_advances' => [
                    'passed' => empty($activeAdvances),
                    'count' => count($activeAdvances),
                    'items' => $activeAdvances,
                ],
                'unreturned_custody' => [
                    'passed' => empty($unreturnedCustody),
                    'count' => count($unreturnedCustody),
                    'items' => $unreturnedCustody,
                ],
            ],
        ];
    }

    /**
     * تنفيذ النقل - تحديث بيانات الموظف
     */
    protected function executeTransfer(Transfer $transfer): void
    {
        $this->transferRepository->executeTransfer($transfer);
    }

    /**
     * الحصول على فروع الشركة
     */
    public function getBranchesByCompany(GetBranchesDTO $dto): array
    {
        return $this->transferRepository->getBranchesByCompany($dto->companyId);
    }
}
