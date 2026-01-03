<?php

namespace App\Services;

use App\DTOs\Leave\CreateHourlyLeaveDTO;
use App\DTOs\Leave\HourlyLeaveFilterDTO;
use App\DTOs\Leave\CancelHourlyLeaveDTO;
use App\DTOs\Leave\ApproveOrRejectHourlyLeaveDTO;
use App\DTOs\Leave\UpdateHourlyLeaveDTO;
use App\Enums\DeductedStatus;
use App\Enums\LeavePlaceEnum;
use App\Enums\NumericalStatusEnum;
use App\Models\LeaveApplication;
use App\Models\User;
use App\Repository\Interface\HourlyLeaveRepositoryInterface;
use App\Services\SimplePermissionService;
use App\Services\NotificationService;
use App\Services\ApprovalWorkflowService;
use App\Services\ApprovalService;
use App\Enums\StringStatusEnum;
use App\Jobs\SendEmailNotificationJob;
use App\Mail\Leave\HourSubmitted;
use App\Mail\Leave\LeaveApproved;
use App\Mail\Leave\LeaveRejected;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HourlyLeaveService
{
    public function __construct(
        protected HourlyLeaveRepositoryInterface $hourlyLeaveRepository,
        protected SimplePermissionService $permissionService,
        protected NotificationService $notificationService,
        protected ApprovalWorkflowService $approvalWorkflow,
        protected CacheService $cacheService,
        protected ApprovalService $approvalService,
    ) {}

    /**
     * الحصول على قائمة طلبات الإستئذان بالساعات مع التصفية
     */
    public function getPaginatedHourlyLeaves(HourlyLeaveFilterDTO $filters, User $user): array
    {
        // إنشاء filters جديد بناءً على صلاحيات المستخدم
        $filterData = $filters->toArray();

        // التحقق من نوع المستخدم (company أو staff فقط)
        if ($user->user_type == 'company') {
            // مدير الشركة: يرى جميع طلبات شركته
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $filterData['company_id'] = $effectiveCompanyId;
        } else {
            // موظف (staff): يرى طلباته + طلبات الموظفين التابعين له بناءً على المستويات الهرمية والقسم
            $subordinateIds = $this->getSubordinateEmployeeIds($user);

            if (!empty($subordinateIds)) {
                // لديه موظفين تابعين: طلباته + طلبات التابعين

                // Filter subordinates based on restrictions
                $subordinateIds = array_filter($subordinateIds, function ($empId) use ($user) {
                    $emp = User::find($empId);
                    if (!$emp) return false;
                    return $this->permissionService->canViewEmployeeRequests($user, $emp);
                });

                $subordinateIds[] = $user->user_id; // إضافة نفسه
                $filterData['employee_ids'] = $subordinateIds;
                $filterData['company_id'] = $user->company_id;
            } else {
                // ليس لديه موظفين تابعين: طلباته فقط
                $filterData['employee_id'] = $user->user_id;
                $filterData['company_id'] = $user->company_id;
            }
        }

        // إضافة أنواع الإجازات المحظورة للمستخدم الحالي (للتصفية)
        // Managers should see all types of requests from their subordinates even if they are personally restricted
        $hasSubordinates = isset($subordinateIds) && !empty($subordinateIds);

        $companyIdForRestriction = $filterData['company_id'] ?? $user->company_id;
        $restrictedIds = $this->getRestrictedLeaveTypeIds($user, $companyIdForRestriction);
        if (!empty($restrictedIds) && !$hasSubordinates) {
            $filterData['excluded_leave_type_ids'] = $restrictedIds;
        }


        // إنشاء DTO جديد مع البيانات المحدثة
        $updatedFilters = HourlyLeaveFilterDTO::fromRequest($filterData);

        $applications = $this->hourlyLeaveRepository->getPaginatedHourlyLeaves($updatedFilters, $user);

        return $applications;
    }

    /**
     * الحصول على جميع معرفات الموظفين التابعين باستخدام المستويات الهرمية والقسم
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
     * الحصول على طلب إستئذان بالساعات بواسطة المعرف
     */
    public function getHourlyLeaveById(int $id, ?int $companyId = null, ?int $userId = null, ?User $user = null): ?LeaveApplication
    {
        $user = $user ?? Auth::user();

        if (is_null($companyId) && is_null($userId)) {
            throw new \InvalidArgumentException('يجب توفير معرف الشركة أو معرف المستخدم');
        }

        // البحث عن الطلب بواسطة معرف الشركة (للمستخدمين من نوع company/admins)
        if ($companyId !== null) {
            $application = $this->hourlyLeaveRepository->findHourlyLeaveById($id, $companyId);

            // Check hierarchy permissions for staff users
            if ($user && $user->user_type !== 'company') {
                // Allow users to view their own requests
                if ($application->employee_id === $user->user_id) {
                    // Still check operation restrictions even for own requests
                    $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
                    $restrictedTypes = $this->permissionService->getRestrictedValues(
                        $user->user_id,
                        $effectiveCompanyId,
                        'leave_type_'
                    );
                    // For own requests, we allow viewing even if restricted (since actions are restricted elsewhere)
                    return $application;
                }

                $employee = User::find($application->employee_id);
                if (!$employee || !$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    Log::warning('HourlyLeaveService::getHourlyLeaveById - Hierarchy permission denied', [
                        'application_id' => $id,
                        'requester_id' => $user->user_id,
                        'requester_type' => $user->user_type,
                        'employee_id' => $application->employee_id,
                        'requester_level' => $this->permissionService->getUserHierarchyLevel($user),
                        'employee_level' => $this->permissionService->getUserHierarchyLevel($employee),
                        'requester_department' => $this->permissionService->getUserDepartmentId($user),
                        'employee_department' => $this->permissionService->getUserDepartmentId($employee)
                    ]);
                    return null;
                }

                // Check operation restrictions for viewing other's requests
                $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
                $restrictedTypes = $this->permissionService->getRestrictedValues(
                    $user->user_id,
                    $effectiveCompanyId,
                    'leave_type_'
                );
                if (in_array($application->leave_type_id, $restrictedTypes) && !$this->permissionService->canOverrideRestriction($user, $employee)) {
                    Log::warning('HourlyLeaveService::getHourlyLeaveById - Operation restriction denied', [
                        'application_id' => $id,
                        'leave_type_id' => $application->leave_type_id,
                        'restricted_types' => $restrictedTypes,
                    ]);
                    return null;
                }
            }
            return $application;
        }

        // البحث عن الطلب بواسطة معرف المستخدم (للموظفين العاديين)
        if ($userId !== null) {
            $application = $this->hourlyLeaveRepository->findHourlyLeaveForEmployee($id, $userId);
            return $application;
        }

        return null;
    }

    /**
     * إنشاء طلب إستئذان بالساعات
     */
    public function createHourlyLeave(CreateHourlyLeaveDTO $dto): object
    {
        return DB::transaction(function () use ($dto) {
            // التحقق من قيود نوع الإجازة
            $user = User::find($dto->employeeId);

            // Check if requester can override restrictions
            $canOverride = false;
            if ($dto->createdBy) {
                $requester = User::find($dto->createdBy);
                if ($requester && $user && $this->permissionService->canOverrideRestriction($requester, $user, 'leave_type_', (int)$dto->leaveTypeId)) {
                    $canOverride = true;
                }
            }

            if ($user) {
                $restrictedIds = $this->getRestrictedLeaveTypeIds($user, $dto->companyId);
                if (!$canOverride && in_array($dto->leaveTypeId, $restrictedIds)) {
                    Log::warning('HourlyLeaveService::createHourlyLeave - Restricted leave type selected', [
                        'employee_id' => $dto->employeeId,
                        'leave_type_id' => $dto->leaveTypeId,
                        'company_id' => $dto->companyId
                    ]);
                    throw new \Exception('نوع الإجازة المختار غير متاح لهذا الموظف');
                }
            }

            // حساب ساعات الإجازة
            $startTime = \Carbon\Carbon::parse($dto->date . ' ' . $dto->clockInM);
            $endTime = \Carbon\Carbon::parse($dto->date . ' ' . $dto->clockOutM);
            $leaveHours = $endTime->diffInHours($startTime);

            // التحقق من أن الساعات أقل من 8 (استئذان وليس إجازة كاملة)
            if ($leaveHours >= 8) {
                Log::info('HourlyLeaveService::createHourlyLeave - Leave hours must be less than 8', [
                    'employee_id' => $dto->employeeId,
                    'leave_type_id' => $dto->leaveTypeId,
                    'date' => $dto->date,
                    'clock_in_m' => $dto->clockInM,
                    'clock_out_m' => $dto->clockOutM,
                    'leave_hours' => $leaveHours,
                    'message' => 'لا يمكن تسجيل استئذان لـ {$leaveHours} ساعة. الاستئذان يجب أن يكون أقل من 8 ساعات. للـ 8 ساعات أو أكثر، يرجى استخدام طلب إجازة عادية.'
                ]);
                throw new \Exception(
                    "لا يمكن تسجيل استئذان لـ {$leaveHours} ساعة. الاستئذان يجب أن يكون أقل من 8 ساعات. للـ 8 ساعات أو أكثر، يرجى استخدام طلب إجازة عادية."
                );
            }

            // التحقق من عدم وجود استئذان آخر في نفس التاريخ
            if ($this->hourlyLeaveRepository->hasHourlyLeaveOnDate($dto->employeeId, $dto->date, $dto->companyId)) {
                Log::info('HourlyLeaveService::createHourlyLeave - Leave already exists on this date', [
                    'employee_id' => $dto->employeeId,
                    'leave_type_id' => $dto->leaveTypeId,
                    'date' => $dto->date,
                    'clock_in_m' => $dto->clockInM,
                    'clock_out_m' => $dto->clockOutM,
                    'message' => 'يوجد لديك استئذان مسجل بالفعل في هذا التاريخ. لا يمكن تسجيل طلب آخر في نفس اليوم.'
                ]);
                throw new \Exception(
                    'يوجد لديك استئذان مسجل بالفعل في هذا التاريخ. لا يمكن تسجيل طلب آخر في نفس اليوم.'
                );
            }

            // جلب الرصيد المتاح لنوع الإجازة
            $availableBalance = $this->getAvailableLeaveBalance(
                $dto->employeeId,
                $dto->leaveTypeId,
                $dto->companyId,
            );

            // إذا كانت الإجازة المطلوبة أكبر من الرصيد المتاح نرفض الطلب
            if ($leaveHours > $availableBalance) {
                Log::info('HourlyLeaveService::createHourlyLeave - Leave hours exceed available balance', [
                    'employee_id' => $dto->employeeId,
                    'leave_type_id' => $dto->leaveTypeId,
                    'date' => $dto->date,
                    'clock_in_m' => $dto->clockInM,
                    'clock_out_m' => $dto->clockOutM,
                    'leave_hours' => $leaveHours,
                    'available_balance' => $availableBalance,
                    'message' => 'ساعات الإجازة المطلوبة ({$leaveHours} ساعة) أكبر من الرصيد المتاح ({$availableBalance} ساعة) لهذا النوع.'
                ]);
                throw new \Exception(
                    "ساعات الإجازة المطلوبة ({$leaveHours} ساعة) أكبر من الرصيد المتاح ({$availableBalance} ساعة) لهذا النوع."
                );
            }

            $leave = $this->hourlyLeaveRepository->createHourlyLeave($dto);

            if (!$leave) {
                Log::info('HourlyLeaveService::createHourlyLeave - Failed to create leave application', [
                    'employee_id' => $dto->employeeId,
                    'leave_type_id' => $dto->leaveTypeId,
                    'date' => $dto->date,
                    'clock_in_m' => $dto->clockInM,
                    'clock_out_m' => $dto->clockOutM,
                    'leave_hours' => $leaveHours,
                    'available_balance' => $availableBalance,
                    'message' => 'فشل في إنشاء طلب الإستئذان للإجازة بـ {$leaveHours} ساعة - الرصيد المتوفر: {$availableBalance} ساعة'
                ]);
                throw new \Exception("فشل في إنشاء طلب الإستئذان للإجازة بـ {$leaveHours} ساعة - الرصيد المتوفر: {$availableBalance} ساعة");
            }

            // بدء سير عمل الموافقة
            $this->approvalWorkflow->submitForApproval(
                'leave_settings',
                (string)$leave->leave_id,
                $dto->employeeId,
                $dto->companyId
            );

            // إرسال الإشعارات
            $this->notificationService->sendSubmissionNotification(
                'leave_settings',
                (string)$leave->leave_id,
                $dto->companyId,
                StringStatusEnum::SUBMITTED->value,
                $dto->employeeId
            );

            // الحصول على بريد الموظف واسمه من العلاقات المحملة مسبقًا
            $employeeEmail = $leave->employee->email ?? null;
            $employeeName = $leave->employee->full_name ?? 'Employee';
            $leaveTypeName = $leave->leaveType->leave_type_name ?? 'Hourly Leave';

            // إرسال إشعار بريد إلكتروني إذا كان بريد الموظف موجودًا (يتم إرساله كـ job)
            if ($employeeEmail) {
                SendEmailNotificationJob::dispatch(
                    new HourSubmitted(
                        employeeName: $employeeName,
                        leaveType: $leaveTypeName,
                        date: $dto->date,
                        hours: (int)($dto->leaveHours ?? 0),
                    ),
                    $employeeEmail
                );
            }
            return $leave;
        });
    }

    /**
     * إلغاء طلب إستئذان بالساعات
     */
    public function cancelHourlyLeave(int $id, User $user): bool
    {
        return DB::transaction(function () use ($id, $user) {
            // Get effective company ID
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // Find application in same company
            $application = $this->hourlyLeaveRepository->findHourlyLeaveById($id, $effectiveCompanyId);

            if (!$application) {
                Log::error('HourlyLeaveService::cancelHourlyLeave - Leave application not found', [
                    'id' => $id,
                    'message' => 'الطلب غير موجود'
                ]);
                throw new \Exception('الطلب غير موجود');
            }

            // 1. Status Check: Only Pending (0) can be cancelled
            if ($application->status !== LeaveApplication::STATUS_PENDING) {
                Log::error('HourlyLeaveService::cancelHourlyLeave - Leave application not pending', [
                    'id' => $id,
                    'message' => 'لا يمكن إلغاء الطلب بعد المراجعة (موافق عليه أو مرفوض)'
                ]);
                throw new \Exception('لا يمكن إلغاء الطلب بعد المراجعة (موافق عليه أو مرفوض)');
            }

            // 2. Permission Check

            // Enforce restrictions on cancel
            $targetEmployee = User::find($application->employee_id);

            // Check if requester can override restrictions
            $canOverride = false;
            if ($user->user_id !== $targetEmployee->user_id) {
                if ($this->permissionService->canOverrideRestriction($user, $targetEmployee, 'leave_type_', (int)$application->leave_type_id)) {
                    $canOverride = true;
                }
            }

            if (!$canOverride && $user->user_type !== 'company') {
                $restrictedIds = $this->getRestrictedLeaveTypeIds($targetEmployee, $effectiveCompanyId);
                if (in_array($application->leave_type_id, $restrictedIds)) {
                    Log::warning('HourlyLeaveService::cancelHourlyLeave - Restricted leave type cancel attempt', [
                        'application_id' => $id,
                        'leave_type_id' => $application->leave_type_id,
                        'user_id' => $user->user_id,
                        'message' => 'نوع الإجازة في هذا الطلب مقيد، لا يمكن إلغاء الطلب.'
                    ]);
                    throw new \Exception('نوع الإجازة في هذا الطلب مقيد، لا يمكن إلغاء الطلب.');
                }
            }

            $isOwner = $application->employee_id === $user->user_id;
            $isCompany = $user->user_type === 'company';

            // Check hierarchy permission (is a manager of the employee)
            $isHierarchyManager = false;
            if (!$isOwner && !$isCompany) {
                $employee = User::find($application->employee_id);
                if ($employee && $this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    $isHierarchyManager = true;
                }
            }

            if (!$isOwner && !$isCompany && !$isHierarchyManager) {
                Log::error('HourlyLeaveService::cancelHourlyLeave - User does not have permission', [
                    'id' => $id,
                    'message' => 'ليس لديك صلاحية لإلغاء هذا الطلب'
                ]);
                throw new \Exception('ليس لديك صلاحية لإلغاء هذا الطلب');
            }

            // Determine cancel reason based on who is cancelling
            $cancelReason = $isOwner
                ? 'تم إلغاء الطلب من قبل الموظف'
                : 'تم إلغاء الطلب من قبل الإدارة';

            // Mark as rejected (keeps record in database)
            $this->hourlyLeaveRepository->rejectHourlyLeave($application, $user->user_id, $cancelReason);

            Log::info('HourlyLeaveService::cancelHourlyLeave - Transaction committed', [
                'application_id' => $id
            ]);

            // Send notification for cancellation
            $this->notificationService->sendSubmissionNotification(
                'leave_settings',
                (string)$id,
                $effectiveCompanyId,
                StringStatusEnum::REJECTED->value,
                $application->employee_id
            );

            return true;
        });
    }

    /**
     * الموافقة على أو رفض طلب إستئذان بالساعات
     */
    public function approveOrRejectHourlyLeave(int $id, ApproveOrRejectHourlyLeaveDTO $dto): object
    {
        return DB::transaction(function () use ($id, $dto) {
            Log::info('HourlyLeaveService::approveOrRejectHourlyLeave - Transaction started', [
                'application_id' => $id,
                'action' => $dto->action
            ]);

            // الحصول على معرف الشركة الفعلي
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());

            // البحث عن الطلب في نفس الشركة
            $application = $this->hourlyLeaveRepository->findHourlyLeaveById($id, $effectiveCompanyId);

            if (!$application) {
                Log::warning('HourlyLeaveService::approveOrRejectHourlyLeave - Application not found', [
                    'application_id' => $id,
                    'message' => 'الطلب غير موجود'
                ]);
                throw new \Exception('الطلب غير موجود');
            }

            // التحقق من أن الطلب لم يتم معالجته مسبقًا
            if ($application->status !== LeaveApplication::STATUS_PENDING) {
                Log::warning('HourlyLeaveService::approveOrRejectHourlyLeave - Application not found', [
                    'application_id' => $id,
                    'message' => 'تم الموافقة على هذا الطلب مسبقاً أو تم رفضه'
                ]);
                throw new \Exception('تم الموافقة على هذا الطلب مسبقاً أو تم رفضه');
            }

            // Check hierarchy permissions for staff users (strict: must be higher level)
            $user = Auth::user();
            if ($user->user_type !== 'company') {
                $employee = User::find($application->employee_id);
                if (!$employee || !$this->permissionService->canApproveEmployeeRequests($user, $employee)) {
                    Log::warning('HourlyLeaveService::approveOrRejectHourlyLeave - Hierarchy permission denied', [
                        'application_id' => $id,
                        'requester_id' => $user->user_id,
                        'requester_type' => $user->user_type,
                        'employee_id' => $application->employee_id,
                        'requester_level' => $this->permissionService->getUserHierarchyLevel($user),
                        'employee_level' => $this->permissionService->getUserHierarchyLevel($employee),
                        'requester_department' => $this->permissionService->getUserDepartmentId($user),
                        'employee_department' => $this->permissionService->getUserDepartmentId($employee),
                        'message' => 'ليس لديك صلاحية لمراجعة طلب هذا الموظف'
                    ]);
                    throw new \Exception('ليس لديك صلاحية لمراجعة طلب هذا الموظف');
                }
            }

            $userType = strtolower(trim($user->user_type ?? ''));

            // Company user can approve/reject directly
            if ($userType === 'company') {
                if ($dto->action === 'approve') {
                    $approvedApplication = $this->hourlyLeaveRepository->approveHourlyLeave(
                        $application,
                        $dto->processedBy,
                        $dto->remarks
                    );

                    if (!$approvedApplication) {
                        throw new \Exception('فشل في الموافقة على الطلب');
                    }

                    // Record final approval
                    $this->approvalService->recordApproval(
                        $approvedApplication->leave_id,
                        $dto->processedBy,
                        1, // approved
                        1, // final level
                        'hourly_leave_settings',
                        $effectiveCompanyId
                    );

                    $this->notificationService->sendApprovalNotification(
                        'hourly_leave_settings',
                        (string)$approvedApplication->leave_id,
                        $effectiveCompanyId,
                        StringStatusEnum::APPROVED->value,
                        $dto->processedBy,
                        null,
                        $approvedApplication->employee_id
                    );

                    // Send approval email
                    $employeeEmail = $approvedApplication->employee->email ?? null;
                    $employeeName = $approvedApplication->employee->full_name ?? 'Employee';
                    $leaveTypeName = $approvedApplication->leaveType->leave_type_name ?? 'Hourly Leave';

                    if ($employeeEmail) {
                        SendEmailNotificationJob::dispatch(
                            new LeaveApproved(
                                employeeName: $employeeName,
                                leaveType: $leaveTypeName,
                                startDate: $approvedApplication->from_date,
                                endDate: $approvedApplication->to_date,
                                remarks: $dto->remarks
                            ),
                            $employeeEmail
                        );
                    }

                    return $approvedApplication;
                } else {
                    // Company rejection
                    $reason = $dto->remarks ?? 'تم رفض الطلب';
                    $processedApplication = $this->hourlyLeaveRepository->rejectHourlyLeave(
                        $application,
                        $dto->processedBy,
                        $reason
                    );

                    if (!$processedApplication) {
                        throw new \Exception('فشل في رفض الطلب');
                    }

                    // Record rejection
                    $this->approvalService->recordApproval(
                        $processedApplication->leave_id,
                        $dto->processedBy,
                        2, // rejected
                        2, // rejection level
                        'hourly_leave_settings',
                        $effectiveCompanyId
                    );

                    $this->notificationService->sendApprovalNotification(
                        'hourly_leave_settings',
                        (string)$processedApplication->leave_id,
                        $effectiveCompanyId,
                        StringStatusEnum::REJECTED->value,
                        $dto->processedBy,
                        null,
                        $processedApplication->employee_id
                    );

                    // Send rejection email
                    $employeeEmail = $processedApplication->employee->email ?? null;
                    $employeeName = $processedApplication->employee->full_name ?? 'Employee';
                    $leaveTypeName = $processedApplication->leaveType->leave_type_name ?? 'Hourly Leave';

                    if ($employeeEmail) {
                        SendEmailNotificationJob::dispatch(
                            new LeaveRejected(
                                employeeName: $employeeName,
                                leaveType: $leaveTypeName,
                                startDate: $processedApplication->from_date,
                                endDate: $processedApplication->to_date,
                                reason: $reason
                            ),
                            $employeeEmail
                        );
                    }

                    return $processedApplication;
                }
            }

            // For staff users, verify approval levels
            $canApprove = $this->approvalService->canUserApprove(
                $user->user_id,
                $application->leave_id,
                $application->employee_id,
                'hourly_leave_settings'
            );

            if (!$canApprove) {
                Log::info('HourlyLeaveService::approveOrRejectHourlyLeave - Multi-level approval denied', [
                    'user_id' => $user->user_id,
                    'leave_id' => $id,
                    'message' => 'ليس لديك صلاحية للموافقة على هذا الطلب في المرحلة الحالية'
                ]);
                throw new \Exception('ليس لديك صلاحية للموافقة على هذا الطلب في المرحلة الحالية');
            }

            if ($dto->action === 'approve') {
                $isFinal = $this->approvalService->isFinalApproval(
                    $application->leave_id,
                    $application->employee_id,
                    'hourly_leave_settings'
                );

                if ($isFinal) {
                    // الموافقة على الطلب
                    $approvedApplication = $this->hourlyLeaveRepository->approveHourlyLeave(
                        $application,
                        $dto->processedBy,
                        $dto->remarks
                    );

                    if (!$approvedApplication) {
                        throw new \Exception('فشل في الموافقة على الطلب');
                    }

                    $this->notificationService->sendApprovalNotification(
                        'hourly_leave_settings',
                        (string)$approvedApplication->leave_id,
                        $effectiveCompanyId,
                        StringStatusEnum::APPROVED->value,
                        $dto->processedBy,
                        null,
                        $approvedApplication->employee_id
                    );
                    // Record final approval
                    $this->approvalService->recordApproval(
                        $approvedApplication->leave_id,
                        $dto->processedBy,
                        1,
                        1,
                        'hourly_leave_settings',
                        $effectiveCompanyId
                    );


                    // Send approval email
                    $employeeEmail = $approvedApplication->employee->email ?? null;
                    $employeeName = $approvedApplication->employee->full_name ?? 'Employee';
                    $leaveTypeName = $approvedApplication->leaveType->leave_type_name ?? 'Leave';

                    if ($employeeEmail) {
                        SendEmailNotificationJob::dispatch(
                            new LeaveApproved(
                                employeeName: $employeeName,
                                leaveType: $leaveTypeName,
                                startDate: $approvedApplication->from_date,
                                endDate: $approvedApplication->to_date,
                                remarks: $dto->remarks
                            ),
                            $employeeEmail
                        );
                    }
                    return $approvedApplication;
                } else {
                    // Intermediate approval - record and notify next level
                    $this->approvalService->recordApproval(
                        $application->leave_id,
                        $user->user_id,
                        1,
                        0,
                        'leave_settings',
                        $effectiveCompanyId
                    );

                    // Send approval notification
                    $this->notificationService->sendApprovalNotification(
                        'leave_settings',
                        (string)$application->leave_id,
                        $effectiveCompanyId,
                        StringStatusEnum::APPROVED->value,
                        $user->user_id,
                        1,
                        $application->employee_id
                    );
                    return $application;
                }
            } else {

                // For staff users, verify approval levels
                if ($userType !== 'company') {
                    $canApprove = $this->approvalService->canUserApprove(
                        $dto->processedBy,
                        $application->leave_id,
                        $application->employee_id,
                        'hourly_leave_settings'
                    );

                    if (!$canApprove) {
                        Log::info('HourlyLeaveService::approveOrRejectHourlyLeave - Multi-level approval denied', [
                            'user_id' => $dto->processedBy,
                            'leave_id' => $application->leave_id,
                            'message' => 'ليس لديك صلاحية لرفض هذا الطلب في المرحلة الحالية'
                        ]);
                        throw new \Exception('ليس لديك صلاحية لرفض هذا الطلب في المرحلة الحالية');
                    }
                }
                // رفض الطلب
                $reason = $dto->remarks ?? 'تم رفض الطلب';
                $processedApplication = $this->hourlyLeaveRepository->rejectHourlyLeave(
                    $application,
                    $dto->processedBy,
                    $reason
                );

                if (!$processedApplication) {
                    Log::info('HourlyLeaveService::approveOrRejectHourlyLeave - Multi-level approval denied', [
                        'user_id' => $dto->processedBy,
                        'leave_id' => $application->leave_id,
                        'message' => 'فشل في رفض الطلب'
                    ]);
                    throw new \Exception('فشل في رفض الطلب');
                }

                // Record rejection
                $this->approvalService->recordApproval(
                    $processedApplication->leave_id,
                    $dto->processedBy,
                    2, // rejected
                    2, // rejection level
                    'hourly_leave_settings',
                    $effectiveCompanyId
                );

                // إرسال إشعار الرفض
                $this->notificationService->sendApprovalNotification(
                    'leave_settings',
                    (string)$processedApplication->leave_id,
                    $effectiveCompanyId,
                    StringStatusEnum::REJECTED->value,
                    $dto->processedBy,
                    null,
                    $processedApplication->employee_id
                );

                // إرسال بريد الرفض
                $employeeEmail = $processedApplication->employee->email ?? null;
                $employeeName = $processedApplication->employee->full_name ?? 'Employee';
                $leaveTypeName = $processedApplication->leaveType->leave_type_name ?? 'Hourly Leave';

                if ($employeeEmail) {
                    SendEmailNotificationJob::dispatch(
                        new LeaveRejected(
                            employeeName: $employeeName,
                            leaveType: $leaveTypeName,
                            startDate: $processedApplication->from_date,
                            endDate: $processedApplication->to_date,
                            reason: $reason
                        ),
                        $employeeEmail
                    );
                }

                return $processedApplication;
            }
        });
    }

    /**
     * تحديث طلب إستئذان بالساعات
     */
    public function updateHourlyLeave(int $id, UpdateHourlyLeaveDTO $dto, User $user): LeaveApplication
    {
        return DB::transaction(function () use ($id, $dto, $user) {
            // الحصول على معرف الشركة الفعلي
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // البحث عن الطلب في نفس الشركة
            $application = $this->hourlyLeaveRepository->findHourlyLeaveById($id, $effectiveCompanyId);

            if (!$application) {
                Log::info('HourlyLeaveService::updateHourlyLeave - Leave application not found', [
                    'id' => $id,
                    'message' => 'الطلب غير موجود'
                ]);
                throw new \Exception('الطلب غير موجود');
            }

            // التحقق من صلاحية التعديل
            $isOwner = $application->employee_id === $user->user_id;

            // Check hierarchy permissions for staff users (non-owners)
            if (!$isOwner && $user->user_type !== 'company') {
                $employee = User::find($application->employee_id);
                if (!$employee || !$this->permissionService->canApproveEmployeeRequests($user, $employee)) {
                    Log::warning('HourlyLeaveService::updateHourlyLeave - Hierarchy permission denied', [
                        'application_id' => $id,
                        'requester_id' => $user->user_id,
                        'requester_type' => $user->user_type,
                        'employee_id' => $application->employee_id,
                        'requester_level' => $this->permissionService->getUserHierarchyLevel($user),
                        'employee_level' => $this->permissionService->getUserHierarchyLevel($employee),
                        'requester_department' => $this->permissionService->getUserDepartmentId($user),
                        'employee_department' => $this->permissionService->getUserDepartmentId($employee)
                    ]);
                    throw new \Exception('ليس لديك صلاحية لتعديل طلب هذا الموظف');
                }
            }

            // التحقق من أن الطلب يمكن تحديثه (فقط الطلبات المعلقة)
            if ($application->status !== LeaveApplication::STATUS_PENDING) {
                Log::info('HourlyLeaveService::updateHourlyLeave - Leave application not pending', [
                    'application_id' => $id,
                    'status' => $application->status,
                    'message' => 'لا يمكن تعديل الطلب بعد المراجعة'
                ]);
                throw new \Exception('لا يمكن تعديل الطلب بعد المراجعة');
            }

            // Enforce restrictions on update
            $targetEmployee = User::find($application->employee_id);

            // Check if requester can override restrictions
            $canOverride = false;
            if ($user->user_id !== $targetEmployee->user_id) {
                if ($this->permissionService->canOverrideRestriction($user, $targetEmployee, 'leave_type_', (int)$application->leave_type_id)) {
                    $canOverride = true;
                }
            }

            if (!$canOverride) {
                $restrictedIds = $this->getRestrictedLeaveTypeIds($targetEmployee, $effectiveCompanyId);
                if (in_array($application->leave_type_id, $restrictedIds)) {
                    Log::warning('HourlyLeaveService::updateHourlyLeave - Restricted leave type update attempt', [
                        'application_id' => $id,
                        'leave_type_id' => $application->leave_type_id,
                        'user_id' => $user->user_id,
                        'message' => 'نوع الإجازة في هذا الطلب مقيد، لا يمكن تعديل الطلب.'
                    ]);
                    throw new \Exception('نوع الإجازة في هذا الطلب مقيد، لا يمكن تعديل الطلب.');
                }
            }

            // التحقق من الساعات إذا تم تحديث الأوقات
            if ($dto->clockInM !== null && $dto->clockOutM !== null && $dto->date !== null) {
                $startTime = \Carbon\Carbon::parse($dto->date . ' ' . $dto->clockInM);
                $endTime = \Carbon\Carbon::parse($dto->date . ' ' . $dto->clockOutM);

                // التحقق من أن وقت النهاية بعد وقت البداية
                if ($endTime <= $startTime) {
                    Log::info('HourlyLeaveService::updateHourlyLeave - End time must be after start time', [
                        'application_id' => $id,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'message' => 'وقت النهاية يجب أن يكون بعد وقت البداية'
                    ]);
                    throw new \Exception('وقت النهاية يجب أن يكون بعد وقت البداية');
                }

                $leaveHours = $endTime->diffInHours($startTime);

                // التحقق من أن الساعات أقل من 8
                if ($leaveHours >= 8) {
                    Log::info('HourlyLeaveService::updateHourlyLeave - Leave hours must be less than 8', [
                        'application_id' => $id,
                        'leave_hours' => $leaveHours,
                        'message' => 'لا يمكن تسجيل استئذان لـ {$leaveHours} ساعة. الاستئذان يجب أن يكون أقل من 8 ساعات.'
                    ]);
                    throw new \Exception(
                        "لا يمكن تسجيل استئذان لـ {$leaveHours} ساعة. الاستئذان يجب أن يكون أقل من 8 ساعات."
                    );
                }

                // التحقق من عدم وجود استئذان آخر في نفس التاريخ (باستثناء الطلب الحالي)
                $existingLeave = LeaveApplication::where('company_id', $effectiveCompanyId)
                    ->where('employee_id', $user->user_id)
                    ->where('particular_date', $dto->date)
                    ->where('leave_hours', '>', 0)
                    ->where('leave_hours', '<', 8)
                    ->whereIn('status', [1, 2])
                    ->where('leave_id', '!=', $id)
                    ->exists();

                if ($existingLeave) {
                    Log::info('HourlyLeaveService::updateHourlyLeave - Leave already exists', [
                        'application_id' => $id,
                        'message' => 'يوجد لديك استئذان مسجل بالفعل في هذا التاريخ. لا يمكن تسجيل طلب آخر في نفس اليوم.'
                    ]);
                    throw new \Exception(
                        'يوجد لديك استئذان مسجل بالفعل في هذا التاريخ. لا يمكن تسجيل طلب آخر في نفس اليوم.'
                    );
                }
            }

            // تحديث الطلب
            $updatedApplication = $this->hourlyLeaveRepository->updateHourlyLeave($application, $dto);

            Log::info('HourlyLeaveService::updateHourlyLeave', [
                'application_id' => $updatedApplication->leave_id,
                'updated_by' => $user->full_name
            ]);

            return $updatedApplication;
        });
    }

    /**
     * الحصول على الرصيد المتاح للإجازة للموظف
     */
    private function getAvailableLeaveBalance(int $employeeId, int $leaveTypeId, int $companyId): float
    {
        // التحقق من أن نوع الإجازة غير محظور
        $user = User::find($employeeId);
        if ($user) {
            $restrictedIds = $this->getRestrictedLeaveTypeIds($user, $companyId);
            if (in_array($leaveTypeId, $restrictedIds)) {
                Log::info('HourlyLeaveService::getAvailableLeaveBalance - Restricted leave type check', [
                    'employee_id' => $employeeId,
                    'leave_type_id' => $leaveTypeId,
                    'message' => 'Returning 0 balance for restricted leave type'
                ]);
                return 0;
            }
        }

        // استخدام LeaveRepository للحصول على الرصيد
        $leaveRepository = app(\App\Repository\Interface\LeaveRepositoryInterface::class);

        // 1. الحصول على إجازة ممنوحة إجمالية
        $totalGranted = $leaveRepository->getTotalGrantedLeave(
            $employeeId,
            $leaveTypeId,
            $companyId
        );

        // 2. الحصول على إجازة مستخدمة إجمالية
        $totalUsed = $leaveRepository->getTotalUsedLeave(
            $employeeId,
            $leaveTypeId,
            $companyId
        );

        // 3. حساب الرصيد المتاح
        return max(0, $totalGranted - $totalUsed);
    }

    public function getHourlyLeaveEnums(): array
    {
        $user = Auth::user();

        // استخدام effective company_id إذا كان company_id للمستخدم هو 0
        $companyId = $user->company_id;
        if ($user->company_id === 0) {
            // استخدام permission service للحصول على effective company id
            $permissionService = app(SimplePermissionService::class);
            $companyId = $permissionService->getEffectiveCompanyId($user);
        }

        Log::info('Getting leave types for user', [
            'user_id' => $user->user_id ?? null,
            'user_company_id' => $user->company_id,
            'effective_company_id' => $companyId
        ]);

        $leavetypes = $this->cacheService->getLeaveTypes($companyId);

        // فلترة أنواع الإجازات المحظورة للموظف
        $restrictedLeaveTypeIds = $this->getRestrictedLeaveTypeIds($user, $companyId);
        if (!empty($restrictedLeaveTypeIds)) {
            $leavetypes = array_values(array_filter($leavetypes, function ($leaveType) use ($restrictedLeaveTypeIds) {
                // Ensure leaveTypeId is correctly extracted handling both array and object formats if cache service changes back
                $leaveTypeId = is_array($leaveType) ? ($leaveType['leave_type_id'] ?? $leaveType['constants_id'] ?? null) : ($leaveType->leave_type_id ?? $leaveType->constants_id ?? null);
                return !in_array((int) $leaveTypeId, $restrictedLeaveTypeIds);
            }));
        }

        return [
            'statuses_string' => StringStatusEnum::toArray(),
            'statuses_numeric' => NumericalStatusEnum::toArray(),
            'leave_types' => $leavetypes,
            'leave_place' => LeavePlaceEnum::toArray(),
            'deducted_status' => DeductedStatus::toArray(),
        ];
    }

    /**
     * الحصول على أنواع الإجازات المحظورة للمستخدم
     */
    protected function getRestrictedLeaveTypeIds(User $user, int $companyId): array
    {
        return $this->permissionService->getRestrictedValues(
            $user->user_id,
            $companyId,
            'leave_type_'
        );
    }
}
