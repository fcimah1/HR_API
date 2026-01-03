<?php

namespace App\Services;

use App\DTOs\Leave\CreateHourlyLeaveDTO;
use App\DTOs\Leave\LeaveApplicationFilterDTO;
use App\DTOs\Leave\CreateLeaveApplicationDTO;
use App\DTOs\Leave\UpdateLeaveApplicationDTO;
use App\DTOs\Leave\LeaveApplicationResponseDTO;
use App\Enums\DeductedStatus;
use App\Enums\LeavePlaceEnum;
use App\Enums\NumericalStatusEnum;
use App\Http\Requests\Leave\ApproveLeaveApplicationRequest;
use App\Models\LeaveApplication;
use App\Models\User;
use App\Repository\Interface\LeaveRepositoryInterface;
use App\Repository\Interface\LeaveTypeRepositoryInterface;
use App\Repository\Interface\UserRepositoryInterface;
use App\Services\SimplePermissionService;
use App\Services\ApprovalService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Enums\StringStatusEnum;
use App\Jobs\SendEmailNotificationJob;
use App\Mail\Leave\LeaveSubmitted;
use App\Mail\Leave\LeaveUpdated;
use App\Mail\Leave\LeaveApproved;
use App\Mail\Leave\LeaveRejected;

class LeaveService
{
    public function __construct(
        protected LeaveRepositoryInterface $leaveRepository,
        protected LeaveTypeRepositoryInterface $leaveTypeRepository,
        protected SimplePermissionService $permissionService,
        protected NotificationService $notificationService,
        protected ApprovalWorkflowService $approvalWorkflow,
        protected UserRepositoryInterface $userRepository,
        protected CacheService $cacheService,
        protected ApprovalService $approvalService,
    ) {}

    /**
     * Get paginated leave applications with filters and permission check
     */



    public function getPaginatedApplications(LeaveApplicationFilterDTO $filters, User $user): array
    {
        // إنشاء filters جديد بناءً على صلاحيات المستخدم
        $filterData = $filters->toArray();

        // التحقق من نوع المستخدم (company أو staff فقط)
        if ($user->user_type == 'company') {
            // مدير الشركة: يرى جميع طلبات شركته
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $filterData['company_id'] = $effectiveCompanyId;
        } else {
            // موظف (staff): يرى طلباته + طلبات الموظفين التابعين له في نفس القسم
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

        // إضافة أنواع الإجازات المحظورة للمستخدم الحالي (للتصفية)
        // Managers should see all types of requests from their subordinates even if they are personally restricted
        $hasSubordinates = isset($subordinateIds) && !empty($subordinateIds);

        $companyIdForRestriction = $filterData['company_id'] ?? $user->company_id;
        // $restrictedIds = $this->getRestrictedLeaveTypeIds($user, $companyIdForRestriction);
        // if (!empty($restrictedIds) && !$hasSubordinates) {
        //     $filterData['excluded_leave_type_ids'] = $restrictedIds;
        // }

        // إنشاء DTO جديد مع البيانات المحدثة
        $updatedFilters = LeaveApplicationFilterDTO::fromRequest($filterData);

        $applications = $this->leaveRepository->getPaginatedApplications($updatedFilters, $user);

        return $applications;
    }


    /**
     * الحصول على قوائم Enums الخاصة بالإجازات
     * 
     * @return array
     */
    public function getLeaveEnums(): array
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
                $leaveTypeId = $leaveType['leave_type_id'] ?? $leaveType['constants_id'] ?? null;
                return !in_array((int) $leaveTypeId, $restrictedLeaveTypeIds);
            }));
        }

        return [
            'statuses_string' => StringStatusEnum::toArray(),
            'statuses_numeric' => NumericalStatusEnum::toArray(),
            'deducted_status' => DeductedStatus::toArray(),
            'leave_place' => LeavePlaceEnum::toArray(),
            'leave_types' => $leavetypes
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


    /**
     * Create a new leave application
     * 
     * @param CreateLeaveApplicationDTO $dto
     * @return array
     * @throws \Exception
     */
    public function createApplication(CreateLeaveApplicationDTO $dto): array
    {
        return DB::transaction(function () use ($dto) {

            // التحقق من قيود نوع الإجازة
            // Check for restricted leave types
            // التحقق من قيود نوع الإجازة
            // Check for restricted leave types
            $user = User::with(['user_details.designation'])->find($dto->employeeId);

            // Check if requester is company owner or superior (can override restrictions)
            $canOverrideRestrictions = false;

            if ($dto->createdBy) {
                $requester = User::find($dto->createdBy);
                if ($requester && $user && $this->permissionService->canOverrideRestriction($requester, $user, 'leave_type_', (int)$dto->leaveTypeId)) {
                    $canOverrideRestrictions = true;
                    Log::info('LeaveService::createApplication - Restriction override allowed', [
                        'requester_id' => $requester->user_id,
                        'target_id' => $user->user_id
                    ]);
                }
            }

            if ($user) {
                $restrictedIds = $this->getRestrictedLeaveTypeIds($user, $dto->companyId);

                // Only enforce restriction if not overriden
                if (!$canOverrideRestrictions && in_array($dto->leaveTypeId, $restrictedIds)) {
                    Log::warning('LeaveService::createApplication - Restricted leave type selected', [
                        'employee_id' => $dto->employeeId,
                        'leave_type_id' => $dto->leaveTypeId,
                        'company_id' => $dto->companyId,
                        'created_by' => $dto->createdBy,
                        'message' => 'نوع الإجازة المختار غير متاح لهذا الموظف'
                    ]);
                    throw new \Exception('نوع الإجازة المختار غير متاح لهذا الموظف');
                }
            }

            // حساب الساعات المطلوبة للإجازة
            if (!is_null($dto->leaveHours) && $dto->leaveHours !== '') {
                $requestedHours = (float) $dto->leaveHours * $dto->getDurationInDays();
            } else {
                $days = $dto->getDurationInDays();
                $requestedHours = $days * 8.0; // 8 ساعات لليوم الواحد
            }


            // جلب الرصيد المتاح لنوع الإجازة
            $availableBalance = $this->getAvailableLeaveBalance(
                $dto->employeeId,
                $dto->leaveTypeId,
                $dto->companyId
            );

            // إذا كانت الإجازة المطلوبة أكبر من الرصيد المتاح نرفض الطلب
            if ($requestedHours > $availableBalance) {
                Log::info('LeaveService::createApplication - Not enough balance', [
                    'employee_id' => $dto->employeeId,
                    'leave_type_id' => $dto->leaveTypeId,
                    'requested_hours' => $requestedHours,
                    'available_balance' => $availableBalance,
                    'message' => 'Not enough balance'
                ]);
                throw new \Exception(
                    'ساعات الإجازة المطلوبة (' . $requestedHours . ' ساعة) أكبر من الرصيد المتاح (' . $availableBalance . ' ساعة) لهذا النوع.'
                );
            }

            $leave = $this->leaveRepository->createApplication($dto);

            if (!$leave) {
                Log::info('LeaveService::createApplication - Failed to create leave application', [
                    'employee_id' => $dto->employeeId,
                    'leave_type_id' => $dto->leaveTypeId,
                    'message' => 'Failed to create leave application'
                ]);
                throw new \Exception('Failed to create leave application');
            }

            // Update leave_hours with calculated requested hours
            $leave->update(['leave_hours' => $requestedHours]);

            // Get employee email and name from already loaded relationships
            $employeeEmail = $leave->employee->email ?? null;
            $employeeName = $leave->employee->full_name ?? 'Employee';
            $leaveTypeName = $leave->leaveType->leave_type_name ?? 'Leave';

            // Start approval workflow if multi-level approval is enabled
            $this->approvalWorkflow->submitForApproval(
                'leave_settings',
                (string)$leave->leave_id,
                $dto->employeeId,
                $dto->companyId
            );

            // Send submission notifications
            $notificationsSent = $this->notificationService->sendSubmissionNotification(
                'leave_settings',
                (string)$leave->leave_id,
                $dto->companyId,
                StringStatusEnum::SUBMITTED->value,
                $dto->employeeId // Submitter ID
            );

            // If no notifications were sent (due to missing/invalid configuration),
            // send a notification to the employee as fallback
            if ($notificationsSent === 0 || $notificationsSent === null || !isset($notificationsSent)) {
                $this->notificationService->sendCustomNotification(
                    'leave_settings',
                    (string)$leave->leave_id,
                    [$dto->employeeId],
                    StringStatusEnum::SUBMITTED->value
                );
            }

            // Send email notification if employee email exists (dispatched as job)
            if ($employeeEmail) {
                SendEmailNotificationJob::dispatch(
                    new LeaveSubmitted(
                        employeeName: $employeeName,
                        leaveType: $leaveTypeName,
                        startDate: $dto->fromDate,
                        endDate: $dto->toDate,
                    ),
                    $employeeEmail
                );
            }

            return LeaveApplicationResponseDTO::fromModel($leave)->toArray();
        });
    }



    /**
     * Get leave application by ID with permission check
     * 
     * @param int $id Application ID
     * @param int|null $companyId Company ID (for company users/admins)
     * @param int|null $userId User ID (for regular employees)
     * @param User|null $user User object
     * @return array|null
     * @throws \Exception
     */
    public function getApplicationById(int $id, ?int $companyId = null, ?int $userId = null, ?User $user = null): ?array
    {
        $user = $user ?? Auth::user();

        if (is_null($companyId) && is_null($userId)) {
            Log::info('LeaveService::getApplicationById - Invalid arguments', [
                'message' => 'يجب توفير معرف الشركة أو معرف المستخدم'
            ]);
            throw new \InvalidArgumentException('يجب توفير معرف الشركة أو معرف المستخدم');
        }

        // Company users can see all applications in their company
        if ($user->user_type === 'company') {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $application = $this->leaveRepository->findApplicationInCompany($id, $effectiveCompanyId);

            if ($application) {
                return LeaveApplicationResponseDTO::fromModel($application)->toArray();
            }
        }
        // Staff users: check hierarchy permissions
        else {
            // First, try to find by user ID (own requests) - but only if this is actually the user's own request
            if ($userId !== null) {
                try {
                    $application = $this->leaveRepository->findApplicationForEmployee($id, $userId);

                    if ($application) {
                        // Check operation restrictions even for own requests
                        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
                        $restrictedTypes = $this->permissionService->getRestrictedValues(
                            $user->user_id,
                            $effectiveCompanyId,
                            'leave_type_'
                        );
                        if (in_array($application->leave_type_id, $restrictedTypes)) {
                            Log::warning('LeaveService::getApplicationById - Operation restriction denied (own request)', [
                                'application_id' => $id,
                                'leave_type_id' => $application->leave_type_id,
                                'restricted_types' => $restrictedTypes,
                            ]);
                            return null;
                        }
                        return LeaveApplicationResponseDTO::fromModel($application)->toArray();
                    }
                } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                    // This is not the user's own request, continue to check hierarchy permissions
                    Log::info('LeaveService::getApplicationById - Not user own request, checking hierarchy', [
                        'application_id' => $id,
                        'message' => 'Not user own request, checking hierarchy',
                        'error_message' => $e->getMessage(),
                        'user_id' => $userId
                    ]);
                }
            }

            // Then, try to find in company and check hierarchy permissions
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $application = $this->leaveRepository->findApplicationInCompany($id, $effectiveCompanyId);

            if ($application) {
                // Check if user can view this employee's requests based on hierarchy
                $employee = User::find($application->employee_id);
                if ($employee) {
                    $canView = $this->permissionService->canViewEmployeeRequests($user, $employee);

                    $requesterLevel = $this->permissionService->getUserHierarchyLevel($user);
                    $employeeLevel = $this->permissionService->getUserHierarchyLevel($employee);
                    $requesterDept = $this->permissionService->getUserDepartmentId($user);
                    $employeeDept = $this->permissionService->getUserDepartmentId($employee);
                    Log::info('LeaveService::getApplicationById - Hierarchy check', [
                        'application_id' => $id,
                        'application_employee_id' => $application->employee_id,
                        'requester_id' => $user->user_id,
                        'requester_type' => $user->user_type,
                        'requester_level' => $requesterLevel,
                        'employee_level' => $employeeLevel,
                        'requester_department' => $requesterDept,
                        'employee_department' => $employeeDept,
                        'can_view' => $canView
                    ]);

                    if ($canView) {
                        // Check operation restrictions
                        $restrictedTypes = $this->permissionService->getRestrictedValues(
                            $user->user_id,
                            $effectiveCompanyId,
                            'leave_type_'
                        );
                        $isOwner = $application->employee_id === $user->user_id;
                        $canOverride = $this->permissionService->canOverrideRestriction($user, $application->employee);


                        if (in_array($application->leave_type_id, $restrictedTypes) && !$canOverride && !$isOwner) {
                            Log::warning('LeaveService::getApplicationById - Operation restriction denied', [
                                'application_id' => $id,
                                'leave_type_id' => $application->leave_type_id,
                                'restricted_types' => $restrictedTypes,
                            ]);
                            return null;
                        }
                        return LeaveApplicationResponseDTO::fromModel($application)->toArray();
                    }
                }
            }
        }

        return null;
    }

    /**
     * Update leave application with permission check
     */
    public function update_Application(int $id, UpdateLeaveApplicationDTO $dto, User $user): ?array
    {
        return DB::transaction(function () use ($id, $dto, $user) {

            // الحصول على معرف الشركة الفعلي
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // البحث عن الطلب في نفس الشركة
            $application = $this->leaveRepository->findApplicationInCompany($id, $effectiveCompanyId);

            if (!$application) {
                Log::info('LeaveService::updateApplication - Application not found', [
                    'application_id' => $id,
                    'message' => 'Application not found'
                ]);
                throw new \Exception('الطلب غير موجود');
            }

            // التحقق من صلاحية التعديل
            $isOwner = $application->employee_id === $user->user_id;
            $isCompany = $user->user_type === 'company';

            // صاحب الطلب يمكنه تعديله
            if (!$isOwner && !$isCompany) {
                // التحقق من صلاحية رؤية طلبات الموظف (تشمل قيود القسم/الفرع/الهرمية)
                $employee = User::find($application->employee_id);
                if (!$employee || !$this->permissionService->canApproveEmployeeRequests($user, $employee)) {
                    Log::error('Unauthorized update attempt', [
                        'user_id' => $user->user_id,
                        'application_id' => $id,
                        'employee_id' => $application->employee_id,
                        'message' => 'ليس لديك صلاحية لتعديل هذا الطلب'
                    ]);
                    throw new \Exception('ليس لديك صلاحية لتعديل هذا الطلب');
                }
            }

            // Check if application can be updated (only pending applications)
            if ($application->status !== LeaveApplication::STATUS_PENDING) {
                Log::error('Unauthorized update attempt', [
                    'application_id' => $id,
                    'message' => 'Application not found'
                ]);
                throw new \Exception('لا يمكن تعديل الطلب بعد المراجعة');
            }

            // Enforce restrictions on update
            $targetEmployee = $application->employee;

            // Check if requester can override restrictions
            // We blindly pass the existing leave type because typically update doesn't change type
            // If UpdateDTO supported changing type, we would check that instead.
            $canOverride = false;
            // Check override: Requester != Target AND canOverrideRestriction
            if ($user->user_id !== $targetEmployee->user_id) {
                if ($this->permissionService->canOverrideRestriction($user, $targetEmployee, 'leave_type_', (int)$application->leave_type_id)) {
                    $canOverride = true;
                }
            }

            if (!$canOverride) {
                $restrictedIds = $this->getRestrictedLeaveTypeIds($targetEmployee, $effectiveCompanyId);
                if (in_array($application->leave_type_id, $restrictedIds)) {
                    Log::warning('LeaveService::update_Application - Restricted leave type update attempt', [
                        'application_id' => $id,
                        'leave_type_id' => $application->leave_type_id,
                        'user_id' => $user->user_id,
                        'message' => 'نوع الإجازة في هذا الطلب مقيد، لا يمكن تعديل الطلب.'
                    ]);
                    throw new \Exception('نوع الإجازة في هذا الطلب مقيد، لا يمكن تعديل الطلب.');
                }
            }

            $updatedApplication = $this->leaveRepository->update_Application($application, $dto);

            // Get employee email and name from already loaded relationships
            $employeeEmail = $updatedApplication->employee->email ?? null;
            $employeeName = $updatedApplication->employee->full_name ?? 'Employee';
            $leaveTypeName = $updatedApplication->leaveType->leave_type_name ?? 'Leave';


            // Send email notification if employee email exists (dispatched as job)
            if ($employeeEmail) {
                SendEmailNotificationJob::dispatch(
                    new LeaveUpdated(
                        employeeName: $employeeName,
                        leaveType: $leaveTypeName,
                        startDate: $updatedApplication->from_date,
                        endDate: $updatedApplication->to_date,
                    ),
                    $employeeEmail
                );
            }

            return LeaveApplicationResponseDTO::fromModel($updatedApplication)->toArray();
        });
    }

    /**
     * Cancel leave application (mark as rejected)
     */
    public function cancelApplication(int $id, User $user): bool
    {
        return DB::transaction(function () use ($id, $user) {

            // Get effective company ID
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // Find application in same company
            $application = $this->leaveRepository->findApplicationInCompany($id, $effectiveCompanyId);

            if (!$application) {
                Log::info('LeaveService::cancelApplication - Application not found', [
                    'id' => $id,
                    'message' => 'Application not found'
                ]);
                throw new \Exception('الطلب غير موجود');
            }

            // 1. Status Check: Only Pending (0) can be cancelled
            if ($application->status !== LeaveApplication::STATUS_PENDING) {
                Log::info('LeaveService::cancelApplication - Application not found', [
                    'id' => $id,
                    'message' => 'Application not found'
                ]);
                throw new \Exception('لا يمكن إلغاء الطلب بعد المراجعة (موافق عليه أو مرفوض)');
            }

            // 2. Permission Check

            // Enforce restrictions on cancel
            $targetEmployee = $application->employee;

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
                    Log::warning('LeaveService::cancelApplication - Restricted leave type cancel attempt', [
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
                Log::info('LeaveService::cancelApplication - Application not found', [
                    'id' => $id,
                    'message' => 'Application not found'
                ]);
                throw new \Exception('ليس لديك صلاحية لإلغاء هذا الطلب');
            }

            // Determine cancel reason based on who is cancelling
            $cancelReason = $isOwner
                ? 'تم إلغاء الطلب من قبل الموظف'
                : 'تم إلغاء الطلب من قبل الإدارة';

            // Mark as rejected (keeps record in database)
            $this->leaveRepository->rejectApplication($application, $user->user_id, $cancelReason);

            Log::info('LeaveService::cancelApplication - Transaction committed', [
                'application_id' => $id,
                'message' => 'Application cancelled'
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
     * Approve leave application
     * 
     * @param int $id Leave application ID
     * @param ApproveLeaveApplicationRequest $request The request containing approval details
     * @return array|null
     * @throws \Exception
     */
    public function approveApplication(int $id, ApproveLeaveApplicationRequest $request): object
    {
        return DB::transaction(function () use ($id, $request) {

            // Get the authenticated user from the request
            $user = $request->user();

            // Get the effective company ID for the user
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            // Find the application in the company
            $application = $this->leaveRepository->findApplicationInCompany($id, $companyId);

            if (!$application) {
                Log::info('LeaveService::approveApplication - Application not found', [
                    'id' => $id,
                    'message' => 'Application not found'
                ]);
                throw new \Exception('الطلب غير موجود');
            }

            // Check if the application is already processed
            if ($application->status !== LeaveApplication::STATUS_PENDING) {
                Log::info('LeaveService::approveApplication - Application not found', [
                    'id' => $id,
                    'message' => 'Application not found'
                ]);
                throw new \Exception('تم الموافقة على هذا الطلب مسبقاً أو تم رفضه');
            }

            // Check hierarchy permissions for staff users (strict: must be higher level)
            if ($user->user_type !== 'company') {
                $employee = User::find($application->employee_id);
                if (!$employee || !$this->permissionService->canApproveEmployeeRequests($user, $employee)) {
                    Log::warning('LeaveService::approveApplication - Hierarchy permission denied', [
                        'application_id' => $id,
                        'requester_id' => $user->user_id,
                        'requester_type' => $user->user_type,
                        'employee_id' => $application->employee_id,
                        'requester_level' => $this->permissionService->getUserHierarchyLevel($user),
                        'employee_level' => $this->permissionService->getUserHierarchyLevel($employee),
                        'requester_department' => $this->permissionService->getUserDepartmentId($user),
                        'employee_department' => $this->permissionService->getUserDepartmentId($employee),
                        'message' => 'ليس لديك صلاحية للموافقة على طلب هذا الموظف'
                    ]);
                    throw new \Exception('ليس لديك صلاحية للموافقة على طلب هذا الموظف');
                }
            }

            // Get the remarks from the validated request
            $validated = $request->validated();
            $remarks = $validated['remarks'] ?? null;

            $userType = strtolower(trim($user->user_type ?? ''));

            // Company user can approve directly
            if ($userType === 'company') {
                $approvedApplication = $this->leaveRepository->approveApplication(
                    $application,
                    $user->user_id,
                    $remarks
                );

                if (!$approvedApplication) {
                    throw new \Exception('فشل في الموافقة على الطلب');
                }

                // Send approval notification
                $this->notificationService->sendApprovalNotification(
                    'leave_settings',
                    (string)$approvedApplication->leave_id,
                    $companyId,
                    StringStatusEnum::APPROVED->value,
                    $user->user_id,
                    1,
                    $approvedApplication->employee_id
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
                            remarks: $remarks
                        ),
                        $employeeEmail
                    );
                }
                // Record final approval
                $this->approvalService->recordApproval(
                    $approvedApplication->leave_id,
                    $user->user_id,
                    1,
                    1,
                    'leave_settings',
                    $companyId
                );

                return LeaveApplicationResponseDTO::fromModel($approvedApplication);
            }

            // For staff users, use multi-level approval
            $canApprove = $this->approvalService->canUserApprove(
                $user->user_id,
                $application->leave_id,
                $application->employee_id,
                'leave_settings'
            );

            if (!$canApprove) {
                Log::info('LeaveService::approveApplication - Multi-level approval denied', [
                    'user_id' => $user->user_id,
                    'leave_id' => $id,
                    'message' => 'ليس لديك صلاحية للموافقة على هذا الطلب في المرحلة الحالية'
                ]);
                throw new \Exception('ليس لديك صلاحية للموافقة على هذا الطلب في المرحلة الحالية');
            }

            $isFinal = $this->approvalService->isFinalApproval(
                $application->leave_id,
                $application->employee_id,
                'leave_settings'
            );

            if ($isFinal) {
                // Final approval - update request status
                $approvedApplication = $this->leaveRepository->approveApplication(
                    $application,
                    $user->user_id,
                    $remarks
                );

                if (!$approvedApplication) {
                    throw new \Exception('فشل في الموافقة على الطلب');
                }

                // Send approval notification
                $this->notificationService->sendApprovalNotification(
                    'leave_settings',
                    (string)$approvedApplication->leave_id,
                    $companyId,
                    StringStatusEnum::APPROVED->value,
                    $user->user_id,
                    null,
                    $approvedApplication->employee_id
                );

                // Record final approval
                $this->approvalService->recordApproval(
                    $approvedApplication->leave_id,
                    $user->user_id,
                    1,
                    1,
                    'leave_settings',
                    $companyId
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
                            remarks: $remarks
                        ),
                        $employeeEmail
                    );
                }

                return LeaveApplicationResponseDTO::fromModel($approvedApplication);
            } else {
                // Intermediate approval - record and notify next level
                $this->approvalService->recordApproval(
                    $application->leave_id,
                    $user->user_id,
                    1,
                    0,
                    'leave_settings',
                    $companyId
                );

                // Send approval notification
                $this->notificationService->sendApprovalNotification(
                    'leave_settings',
                    (string)$application->leave_id,
                    $companyId,
                    StringStatusEnum::APPROVED->value,
                    $user->user_id,
                    1,
                    $application->employee_id
                );

                $application->refresh();
                $application->load(['employee', 'approvals.staff']);
                // Return current application state
                return LeaveApplicationResponseDTO::fromModel($application);
            }
        });
    }

    /**
     * Reject leave application
     */
    public function rejectApplication(int $id, int $companyId, int $rejectedBy, string $reason): ?array
    {
        return DB::transaction(function () use ($id, $companyId, $rejectedBy, $reason) {
            $application = $this->leaveRepository->findApplicationInCompany($id, $companyId);

            if (!$application) {
                Log::info('LeaveService::rejectApplication - Application not found', [
                    'id' => $id,
                    'message' => 'الطلب غير موجود'
                ]);
                throw new \Exception('الطلب غير موجود');
            }

            if ($application->status !== LeaveApplication::STATUS_PENDING) {
                Log::info('LeaveService::rejectApplication - Application not pending', [
                    'id' => $id,
                    'message' => 'لا يمكن رفض طلب تم الموافقة عليه مسبقاً'
                ]);
                throw new \Exception('لا يمكن رفض طلب تم الموافقة عليه مسبقاً');
            }

            // Check hierarchy permissions for staff users (strict: must be higher level)
            $rejectingUser = User::find($rejectedBy);
            if ($rejectingUser && $rejectingUser->user_type !== 'company') {
                $employee = User::find($application->employee_id);
                if (!$employee || !$this->permissionService->canApproveEmployeeRequests($rejectingUser, $employee)) {
                    Log::warning('LeaveService::rejectApplication - Hierarchy permission denied', [
                        'application_id' => $id,
                        'rejecting_user_id' => $rejectedBy,
                        'rejecting_user_type' => $rejectingUser->user_type,
                        'employee_id' => $application->employee_id,
                        'rejecting_user_level' => $this->permissionService->getUserHierarchyLevel($rejectingUser),
                        'employee_level' => $this->permissionService->getUserHierarchyLevel($employee),
                        'rejecting_user_department' => $this->permissionService->getUserDepartmentId($rejectingUser),
                        'employee_department' => $this->permissionService->getUserDepartmentId($employee),
                        'message' => 'ليس لديك صلاحية لرفض طلب هذا الموظف'
                    ]);
                    throw new \Exception('ليس لديك صلاحية لرفض طلب هذا الموظف');
                }
            }

            // For staff users, verify approval levels
            $userType = strtolower(trim($rejectingUser->user_type ?? ''));

            // Company user can reject directly
            if ($userType === 'company') {
                $rejectedApplication = $this->leaveRepository->rejectApplication($application, $rejectedBy, $reason);

                if (!$rejectedApplication) {
                    throw new \Exception('فشل في رفض الطلب');
                }

                // Record rejection
                $this->approvalService->recordApproval(
                    $rejectedApplication->leave_id,
                    $rejectedBy,
                    2, // rejected
                    2, // rejection level
                    'leave_settings',
                    $companyId
                );

                // Send rejection notification
                $this->notificationService->sendApprovalNotification(
                    'leave_settings',
                    (string)$rejectedApplication->leave_id,
                    $companyId,
                    StringStatusEnum::REJECTED->value,
                    $rejectedBy,
                    null,
                    $rejectedApplication->employee_id
                );

                // Send rejection email
                $employeeEmail = $rejectedApplication->employee->email ?? null;
                $employeeName = $rejectedApplication->employee->full_name ?? 'Employee';
                $leaveTypeName = $rejectedApplication->leaveType->leave_type_name ?? 'Leave';

                if ($employeeEmail) {
                    SendEmailNotificationJob::dispatch(
                        new LeaveRejected(
                            employeeName: $employeeName,
                            leaveType: $leaveTypeName,
                            startDate: $rejectedApplication->from_date,
                            endDate: $rejectedApplication->to_date,
                            reason: $reason
                        ),
                        $employeeEmail
                    );
                }

                return LeaveApplicationResponseDTO::fromModel($rejectedApplication)->toArray();
            }

            // For staff users, verify approval levels
            $canApprove = $this->approvalService->canUserApprove(
                $rejectedBy,
                $application->leave_id,
                $application->employee_id,
                'leave_settings'
            );

            if (!$canApprove) {
                Log::warning('LeaveService::rejectApplication - Approval level denied', [
                    'application_id' => $id,
                    'requester_id' => $rejectedBy,
                    'requester_type' => $rejectingUser->user_type,
                    'employee_id' => $application->employee_id,
                    'requester_level' => $this->permissionService->getUserHierarchyLevel($rejectingUser),
                    'message' => 'ليس لديك صلاحية لرفض هذا الطلب في المرحلة الحالية',
                ]);
                throw new \Exception('ليس لديك صلاحية لرفض هذا الطلب في المرحلة الحالية');
            }

            $rejectedApplication = $this->leaveRepository->rejectApplication($application, $rejectedBy, $reason);

            if (!$rejectedApplication) {
                Log::info('LeaveService::rejectApplication - Application not found', [
                    'id' => $id,
                    'message' => 'Application not found'
                ]);
                throw new \Exception('فشل في رفض الطلب');
            }

            // Record rejection
            $this->approvalService->recordApproval(
                $rejectedApplication->leave_id,
                $rejectedBy,
                2, // rejected
                2, // rejection level
                'leave_settings',
                $companyId
            );

            // Send rejection notification
            $this->notificationService->sendApprovalNotification(
                'leave_settings',
                (string)$rejectedApplication->leave_id,
                $companyId,
                StringStatusEnum::REJECTED->value,
                $rejectedBy,
                null,
                $rejectedApplication->employee_id
            );

            // Send rejection email
            $employeeEmail = $rejectedApplication->employee->email ?? null;
            $employeeName = $rejectedApplication->employee->full_name ?? 'Employee';
            $leaveTypeName = $rejectedApplication->leaveType->leave_type_name ?? 'Leave';

            if ($employeeEmail) {
                SendEmailNotificationJob::dispatch(
                    new LeaveRejected(
                        employeeName: $employeeName,
                        leaveType: $leaveTypeName,
                        startDate: $rejectedApplication->from_date,
                        endDate: $rejectedApplication->to_date,
                        reason: $reason
                    ),
                    $employeeEmail
                );
            }

            return LeaveApplicationResponseDTO::fromModel($rejectedApplication)->toArray();
        });
    }

    /**
     * Get leave statistics
     */
    public function getLeaveStatistics(int $companyId): array
    {
        return $this->leaveRepository->getLeaveStatistics($companyId,);
    }



    /**
     * Get available leave balance for an employee
     *
     * @param int $employeeId
     * @param int $leaveTypeId
     * @param int $companyId
     * @return float
     */
    public function getAvailableLeaveBalance(int $employeeId, int $leaveTypeId, int $companyId): float
    {
        // التحقق من أن نوع الإجازة غير محظور
        $user = User::find($employeeId);
        if ($user) {
            $restrictedIds = $this->getRestrictedLeaveTypeIds($user, $companyId);
            if (in_array($leaveTypeId, $restrictedIds)) {
                Log::info('LeaveService::getAvailableLeaveBalance - Restricted leave type check', [
                    'employee_id' => $employeeId,
                    'leave_type_id' => $leaveTypeId,
                    'message' => 'Returning 0 balance for restricted leave type'
                ]);
                return 0;
            }
        }

        // 1. Get total granted leave
        $totalGranted = $this->leaveRepository->getTotalGrantedLeave(
            $employeeId,
            $leaveTypeId,
            $companyId
        );

        // 2. Get total used leave
        $totalUsed = $this->leaveRepository->getTotalUsedLeave(
            $employeeId,
            $leaveTypeId,
            $companyId
        );

        // 3. Calculate available balance
        return max(0, $totalGranted - $totalUsed);
    }

    /**
     * Get detailed leave summary (hours & days) for an employee
     */
    public function getDetailedLeaveSummary(int $employeeId, int $companyId, ?int $leaveTypeId = null): array
    {
        $hoursPerDay = 8.0;

        // Get active leave types for the company
        $leaveTypesData = $this->leaveTypeRepository->getActiveLeaveTypes($companyId);

        // Handle both array and collection responses
        $leaveTypes = collect($leaveTypesData['data'] ?? $leaveTypesData)->map(function ($constant) {
            if (is_array($constant)) {
                return [
                    'leave_type_id' => $constant['constants_id'] ?? $constant['leave_type_id'] ?? null,
                    'leave_type_name' => $constant['category_name'] ?? $constant['leave_type_name'] ?? null,
                    'leave_type_short_name' => $constant['field_one'] ?? $constant['leave_type_short_name'] ?? null,
                    'leave_days' => (float) ($constant['field_two'] ?? $constant['leave_days'] ?? 0),
                    'leave_type_status' => $constant['field_three'] ?? $constant['leave_type_status'] ?? true,
                    'company_id' => $constant['company_id'] ?? null,
                ];
            }

            // Handle object case
            return [
                'leave_type_id' => $constant->constants_id ?? $constant->leave_type_id ?? null,
                'leave_type_name' => $constant->category_name ?? $constant->leave_type_name ?? null,
                'leave_type_short_name' => $constant->field_one ?? $constant->leave_type_short_name ?? null,
                'leave_days' => (float) ($constant->field_two ?? $constant->leave_days ?? 0),
                'leave_type_status' => $constant->field_three ?? $constant->leave_type_status ?? true,
                'company_id' => $constant->company_id ?? null,
            ];
        })->filter()->values()->toArray();

        // Filter restricted leave types
        $user = User::find($employeeId);
        if ($user) {
            $restrictedIds = $this->getRestrictedLeaveTypeIds($user, $companyId);
            if (!empty($restrictedIds)) {
                $leaveTypes = array_values(array_filter($leaveTypes, function ($type) use ($restrictedIds) {
                    return !in_array((int)($type['leave_type_id'] ?? 0), $restrictedIds);
                }));
            }
        }



        if ($leaveTypeId !== null) {
            $leaveTypes = array_values(array_filter($leaveTypes, function (array $type) use ($leaveTypeId) {
                return (int) ($type['leave_type_id'] ?? 0) === $leaveTypeId;
            }));
        }

        $items = [];

        $totals = [
            'granted_hours' => 0.0,
            'used_hours' => 0.0,
            'pending_hours' => 0.0,
            'adjustment_hours' => 0.0,
            'balance_hours' => 0.0,
            'remaining_hours' => 0.0,
        ];

        $currentYear = (int) date('Y');

        foreach ($leaveTypes as $type) {
            $typeId = (int) ($type['leave_type_id'] ?? 0);
            if (! $typeId) {
                continue;
            }

            $granted = $this->leaveRepository->getTotalGrantedLeave($employeeId, $typeId, $companyId);
            $used = $this->leaveRepository->getTotalUsedLeave($employeeId, $typeId, $companyId);
            $pending = $this->leaveRepository->getPendingLeaveHours($employeeId, $typeId, $companyId);
            $adjustments = $this->leaveRepository->getTotalAdjustmentHours($employeeId, $typeId, $companyId);

            $entitled = $granted + $adjustments;
            $balance = $entitled - $used;
            $remaining = $balance - $pending;

            $items[] = [
                'leave_type_id' => $typeId,
                'leave_type_name' => $type['leave_type_name'] ?? null,
                'leave_type_short_name' => $type['leave_type_short_name'] ?? null,
                'year' => $currentYear,

                // بالساعات
                'granted_hours' => (float) $granted,
                'used_hours' => (float) $used,
                'pending_hours' => (float) $pending,
                'adjustment_hours' => (float) $adjustments,
                'entitled_hours' => (float) $entitled,
                'balance_hours' => (float) $balance,
                'remaining_hours' => (float) $remaining,

                // بالأيام
                'granted_days' => (float) round($granted / $hoursPerDay, 2),
                'used_days' => (float) round($used / $hoursPerDay, 2),
                'pending_days' => (float) round($pending / $hoursPerDay, 2),
                'adjustment_days' => (float) round($adjustments / $hoursPerDay, 2),
                'entitled_days' => (float) round($entitled / $hoursPerDay, 2),
                'balance_days' => (float) round($balance / $hoursPerDay, 2),
                'remaining_days' => (float) round($remaining / $hoursPerDay, 2),
            ];

            $totals['granted_hours'] += $granted;
            $totals['used_hours'] += $used;
            $totals['pending_hours'] += $pending;
            $totals['adjustment_hours'] += $adjustments;
            $totals['balance_hours'] += $balance;
            $totals['remaining_hours'] += $remaining;
        }

        $totalsWithDays = [
            'granted_hours' => (float) $totals['granted_hours'],
            'used_hours' => (float) $totals['used_hours'],
            'pending_hours' => (float) $totals['pending_hours'],
            'adjustment_hours' => (float) $totals['adjustment_hours'],
            'balance_hours' => (float) $totals['balance_hours'],
            'remaining_hours' => (float) $totals['remaining_hours'],

            'granted_days' => (float) round($totals['granted_hours'] / $hoursPerDay, 2),
            'used_days' => (float) round($totals['used_hours'] / $hoursPerDay, 2),
            'pending_days' => (float) round($totals['pending_hours'] / $hoursPerDay, 2),
            'adjustment_days' => (float) round($totals['adjustment_hours'] / $hoursPerDay, 2),
            'balance_days' => (float) round($totals['balance_hours'] / $hoursPerDay, 2),
            'remaining_days' => (float) round($totals['remaining_hours'] / $hoursPerDay, 2),
        ];

        return [
            'employee_id' => $employeeId,
            'company_id' => $companyId,
            'hours_per_day' => $hoursPerDay,
            'total' => $totalsWithDays,
            'items' => $items,
        ];
    }

    /**
     * Get monthly leave statistics for an employee
     * Returns detailed monthly breakdown for each leave type
     * 
     * @param int $employeeId
     * @param int $companyId
     * @return array
     */
    public function getMonthlyLeaveStatistics(int $employeeId, int $companyId): array
    {
        $currentYear = (int) date('Y');

        // Get active leave types for the company
        $leaveTypesData = $this->leaveTypeRepository->getActiveLeaveTypes($companyId);

        // Handle both array and collection responses
        $leaveTypes = collect($leaveTypesData['data'] ?? $leaveTypesData)->map(function ($constant) {
            if (is_array($constant)) {
                return [
                    'leave_type_id' => $constant['constants_id'] ?? $constant['leave_type_id'] ?? null,
                    'leave_type_name' => $constant['category_name'] ?? $constant['leave_type_name'] ?? null,
                    'leave_type_short_name' => $constant['field_one'] ?? $constant['leave_type_short_name'] ?? null,
                    'leave_days' => (float) ($constant['field_two'] ?? $constant['leave_days'] ?? 0),
                    'field_one' => $constant['field_one'] ?? null,
                ];
            }

            // Handle object case
            return [
                'leave_type_id' => $constant->constants_id ?? $constant->leave_type_id ?? null,
                'leave_type_name' => $constant->category_name ?? $constant->leave_type_name ?? null,
                'leave_type_short_name' => $constant->field_one ?? $constant->leave_type_short_name ?? null,
                'leave_days' => (float) ($constant->field_two ?? $constant->leave_days ?? 0),
                'field_one' => $constant->field_one ?? null,
            ];
        })->filter()->values()->toArray();

        // فلترة أنواع الإجازات المحظورة للموظف
        $user = User::find($employeeId);
        if ($user) {
            $restrictedIds = $this->getRestrictedLeaveTypeIds($user, $companyId);
            if (!empty($restrictedIds)) {
                $leaveTypes = array_values(array_filter($leaveTypes, function ($type) use ($restrictedIds) {
                    return !in_array((int)($type['leave_type_id'] ?? 0), $restrictedIds);
                }));
            }
        }

        // Get employee details for assigned_hours
        $employee =  $user; // Already found above
        $assignedHours = [];

        if ($employee) {
            $details = $employee->user_details()->first();
            if ($details && !empty($details->assigned_hours)) {
                $assignedHours = @unserialize($details->assigned_hours);
                if (!is_array($assignedHours)) {
                    $assignedHours = [];
                }
            }
        }

        $result = [];

        foreach ($leaveTypes as $type) {
            $typeId = (int) ($type['leave_type_id'] ?? 0);
            if (!$typeId) {
                continue;
            }

            // Get assigned hours for this leave type
            $typeAssignedHours = $assignedHours[$typeId] ?? 0;

            // Check if leave accrual is enabled for this type
            $fieldOne = $type['field_one'] ?? '';
            $leaveOptions = @unserialize($fieldOne);
            $enableLeaveAccrual = false;

            if (is_array($leaveOptions) && isset($leaveOptions['enable_leave_accrual'])) {
                $enableLeaveAccrual = $leaveOptions['enable_leave_accrual'] == 1;
            }

            // Get monthly data
            $monthlyGranted = $this->leaveRepository->getMonthlyGrantedHours($employeeId, $typeId, $companyId);
            $monthlyUsed = $this->leaveRepository->getMonthlyUsedHours($employeeId, $typeId, $companyId, $currentYear);

            // Calculate monthly remaining
            $monthlyBreakdown = [];
            $months = [
                1 => 'Jan',
                2 => 'Feb',
                3 => 'Mar',
                4 => 'Apr',
                5 => 'May',
                6 => 'Jun',
                7 => 'Jul',
                8 => 'Aug',
                9 => 'Sep',
                10 => 'Oct',
                11 => 'Nov',
                12 => 'Dec'
            ];

            foreach ($months as $monthNum => $monthName) {
                $granted = $monthlyGranted[$monthNum] ?? 0.0;
                $used = $monthlyUsed[$monthNum] ?? 0.0;
                $remaining = $granted - $used;

                $monthlyBreakdown[$monthNum] = [
                    'month_name' => $monthName,
                    'granted' => (float) $granted,
                    'used' => (float) $used,
                    'remaining' => (float) $remaining,
                ];
            }

            $result[] = [
                'leave_type_id' => $typeId,
                'leave_type_name' => $type['leave_type_name'] ?? null,
                'leave_type_short_name' => $type['leave_type_short_name'] ?? null,
                'assigned_hours' => (float) $typeAssignedHours,
                'enable_leave_accrual' => $enableLeaveAccrual,
                'monthly_breakdown' => $monthlyBreakdown,
            ];
        }

        return [
            'employee_id' => $employeeId,
            'company_id' => $companyId,
            'year' => $currentYear,
            'leave_types' => $result,
        ];
    }
}
