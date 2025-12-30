<?php

namespace App\Services;

use App\DTOs\LeaveAdjustment\LeaveAdjustmentFilterDTO;
use App\DTOs\LeaveAdjustment\CreateLeaveAdjustmentDTO;
use App\DTOs\LeaveAdjustment\UpdateLeaveAdjustmentDTO;
use App\Models\LeaveAdjustment;
use App\Models\User;
use App\Repository\Interface\LeaveAdjustmentRepositoryInterface;
use App\Repository\Interface\UserRepositoryInterface;
use App\Services\SimplePermissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendEmailNotificationJob;
use App\Mail\LeaveAdjustment\LeaveAdjustmentApproved;
use App\Mail\LeaveAdjustment\LeaveAdjustmentRejected;
use App\Mail\LeaveAdjustment\LeaveAdjustmentSubmitted;
use App\Enums\StringStatusEnum;
use App\Models\LeaveApplication;
use Illuminate\Support\Facades\Auth;

class LeaveAdjustmentService
{
    protected $permissionService;
    protected $leaveService;
    protected $leaveAdjustmentRepository;
    protected $notificationService;
    protected $userRepository;
    protected $cacheService;

    public function __construct(
        SimplePermissionService $permissionService,
        LeaveService $leaveService,
        LeaveAdjustmentRepositoryInterface $leaveAdjustmentRepository,
        NotificationService $notificationService,
        UserRepositoryInterface $userRepository,
        CacheService $cacheService
    ) {
        $this->permissionService = $permissionService;
        $this->leaveService = $leaveService;
        $this->leaveAdjustmentRepository = $leaveAdjustmentRepository;
        $this->notificationService = $notificationService;
        $this->userRepository = $userRepository;
        $this->cacheService = $cacheService;
    }

    /**
     * Get paginated leave adjustments with permission check
     */
    public function getAdjustments(int $userId): array
    {
        $user = User::findOrFail($userId);

        // Create filter array instead of DTO directly
        $filterData = [];

        // Apply company filter based on user permissions
        if ($this->permissionService->isCompanyOwner($user)) {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $filterData['company_id'] = $effectiveCompanyId;
        }

        // Create DTO from filter data
        $filters = LeaveAdjustmentFilterDTO::fromRequest($filterData);

        $result = $this->leaveAdjustmentRepository->getPaginatedAdjustments($filters);

        return [
            'created_by' => $user->full_name,
            'company_id' => $user->company_id,
            ...$result
        ];
    }


    /**
     * Get paginated leave adjustments
     */
    public function getPaginatedAdjustments(LeaveAdjustmentFilterDTO $filters, User $user): array
    {
        // إنشاء filters جديد بناءً على صلاحيات المستخدم
        $filterData = $filters->toArray();

        // إذا كان صاحب الشركة، يمكنه رؤية جميع طلبات شركته
        if ($this->permissionService->isCompanyOwner($user)) {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $filterData['company_id'] = $effectiveCompanyId;
        }
        // إذا كان مدير/إداري (admin/hr/manager) في الشركة، يمكنه رؤية جميع طلبات الشركة
        elseif (in_array(strtolower($user->user_type), ['admin', 'hr', 'manager'])) {
            $filterData['company_id'] = $user->company_id;
        }
        // إذا كان موظف لديه صلاحية عرض جميع الطلبات
        elseif ($this->permissionService->checkPermission($user, 'leave.view.all')) {
            $filterData['company_id'] = $user->company_id;
        }
        // إذا كان موظف عادي، يرى طلباته الشخصية أو طلبات مرؤوسيه إذا كان مديراً
        else {
            // استخدام المنطق المشابه لـ LeaveService
            $subordinateIds = $this->userRepository->getSubordinateEmployeeIds($user->user_id);

            if (!empty($subordinateIds)) {
                // لديه موظفين تابعين: طلباته + طلبات التابعين
                $subordinateIds[] = $user->user_id; // إضافة نفسه
                $filterData['employee_ids'] = $subordinateIds;
                $filterData['company_id'] = $user->company_id;
            } else {
                // ليس لديه موظفين تابعين: طلباته فقط
                $filterData['employee_id'] = $user->user_id;
                $filterData['company_id'] = $user->company_id;
            }
        }

        $updatedFilters = LeaveAdjustmentFilterDTO::fromRequest($filterData);

        $result = $this->leaveAdjustmentRepository->getPaginatedAdjustments($updatedFilters);

        return [
            'created by' => $user->full_name,
            ...$result
        ];
    }
    /**
     * Create leave adjustment
     */
    public function createAdjust(CreateLeaveAdjustmentDTO $data): array
    {
        return DB::transaction(function () use ($data) {

            // التحقق من قيود نوع الإجازة
            $user = User::find($data->employeeId);
            if ($user) {
                $restrictedIds = $this->getRestrictedLeaveTypeIds($user, $data->companyId);
                if (in_array($data->leaveTypeId, $restrictedIds)) {
                    Log::warning('LeaveAdjustmentService::createAdjust - Restricted leave type selected', [
                        'employee_id' => $data->employeeId,
                        'leave_type_id' => $data->leaveTypeId,
                        'company_id' => $data->companyId
                    ]);
                    throw new \Exception('نوع الإجازة المختار غير متاح لهذا الموظف');
                }
            }

            // إذا كانت التسوية خصم من رصيد الإجازات (ساعات سالبة)، تحقق من أن الرصيد يكفي
            if ($data->adjustHours < 0) {
                $availableBalance = $this->getAvailableLeaveBalance(
                    $data->employeeId,
                    $data->leaveTypeId,
                    $data->companyId
                );

                $hoursToDeduct = abs($data->adjustHours);

                if ($availableBalance < $hoursToDeduct) {
                    Log::info('LeaveAdjustmentService::createAdjustment - Not enough balance', [
                        'employee_id' => $data->employeeId,
                        'leave_type_id' => $data->leaveTypeId,
                        'message' => 'Not enough balance',
                        'adjust_hours' => $data->adjustHours,
                        'available_balance' => $availableBalance,
                        'hours_to_deduct' => $hoursToDeduct
                    ]);
                    throw new \Exception(
                        'الرصيد المتاح (' . $availableBalance . ' ساعة) غير كافٍ لتسوية ' . $hoursToDeduct . ' ساعة.'
                    );
                }
            }

            $adjustment = $this->leaveAdjustmentRepository->createAdjust($data);

            // Send notification
            $this->notificationService->sendSubmissionNotification(
                'leave_adjustment_settings',
                (string)$adjustment->id,
                $data->companyId,
                StringStatusEnum::PENDING->value
            );

            // Send email notification
            $employeeEmail = $adjustment->employee->email ?? null;
            $employeeName = $adjustment->employee->full_name ?? 'Employee';
            $leaveTypeName = $adjustment->leaveType->leave_type_name ?? 'Leave Adjustment';

            if ($employeeEmail) {
                SendEmailNotificationJob::dispatch(
                    new LeaveAdjustmentSubmitted(
                        employeeName: $employeeName,
                        leaveType: $leaveTypeName,
                        adjustHours: $data->adjustHours,
                        reason: $data->reasonAdjustment
                    ),
                    $employeeEmail
                );
            }

            return $adjustment->toArray();
        });
    }

    /**
     * Approve leave adjustment
     */
    public function approveAdjustment(int $id, int $companyId, int $approvedBy): LeaveAdjustment
    {
        return DB::transaction(function () use ($id, $companyId, $approvedBy) {
            $adjustment = $this->leaveAdjustmentRepository->findAdjustmentInCompany($id, $companyId);

            if (!$adjustment) {
                Log::info('LeaveAdjustmentService::approveAdjustment - Adjustment not found', [
                    'id' => $id,
                    'message' => 'Adjustment not found',
                    'company_id' => $companyId
                ]);
                throw new \Exception('تسوية الإجازة غير موجودة أو لا تنتمي إلى هذه الشركة');
            }

            if ($adjustment->status !== LeaveAdjustment::STATUS_PENDING) {
                Log::info('LeaveAdjustmentService::approveAdjustment - Adjustment not pending', [
                    'id' => $id,
                    'message' => 'Adjustment not pending',
                    'company_id' => $companyId,
                    'status' => $adjustment->status
                ]);
                throw new \Exception('لا يمكن الموافقة على هذا الطلب لأنه تم معالجته مسبقاً');
            }

            // Check hierarchy permissions for staff users
            $approvingUser = User::find($approvedBy);
            if ($approvingUser && $approvingUser->user_type !== 'company') {
                $employee = User::find($adjustment->employee_id);
                if (!$employee || !$this->permissionService->canViewEmployeeRequests($approvingUser, $employee)) {
                    Log::warning('LeaveAdjustmentService::approveAdjustment - Hierarchy permission denied', [
                        'adjustment_id' => $id,
                        'approver_id' => $approvedBy,
                        'message' => 'Hierarchy permission denied',
                        'employee_id' => $adjustment->employee_id
                    ]);
                    throw new \Exception('ليس لديك صلاحية للموافقة على طلب هذا الموظف');
                }
            }

            $approvedAdjustment = $this->leaveAdjustmentRepository->approveAdjustment($adjustment, $approvedBy);

            // Send approval notification
            $this->notificationService->sendApprovalNotification(
                'leave_adjustment_settings',
                (string)$approvedAdjustment->id,
                $companyId,
                StringStatusEnum::APPROVED->value,
                $approvedBy  // Approver ID
            );

            // Send email notification
            $employeeEmail = $approvedAdjustment->employee->email ?? null;
            $employeeName = $approvedAdjustment->employee->full_name ?? 'Employee';
            $leaveTypeName = $approvedAdjustment->leaveType->leave_type_name ?? 'Leave Adjustment';

            if ($employeeEmail) {
                SendEmailNotificationJob::dispatch(
                    new LeaveAdjustmentApproved(
                        employeeName: $employeeName,
                        leaveType: $leaveTypeName,
                        adjustHours: $approvedAdjustment->adjust_hours,
                        reason: $approvedAdjustment->reason_adjustment,
                        remarks: null
                    ),
                    $employeeEmail
                );
            }

            return $approvedAdjustment;
        });
    }

    /**
     * Reject leave adjustment
     */
    public function rejectAdjustment(int $id, int $companyId, int $rejectedBy, string $reason): LeaveAdjustment
    {
        return DB::transaction(function () use ($id, $companyId, $rejectedBy, $reason) {
            $adjustment = $this->leaveAdjustmentRepository->findAdjustmentInCompany($id, $companyId);

            if (!$adjustment) {
                Log::info('LeaveAdjustmentService::rejectAdjustment - Adjustment not found', [
                    'adjustment_id' => $id,
                    'message' => 'Adjustment not found',
                    'company_id' => $companyId
                ]);
                throw new \Exception('تسوية الإجازة غير موجودة أو لا تنتمي إلى هذه الشركة');
            }

            if ($adjustment->status !== LeaveAdjustment::STATUS_PENDING) {
                Log::info('LeaveAdjustmentService::rejectAdjustment - Adjustment not pending', [
                    'adjustment_id' => $id,
                    'message' => 'Adjustment not pending',
                    'company_id' => $companyId,
                    'status' => $adjustment->status
                ]);
                throw new \Exception('لا يمكن رفض هذا الطلب لأنه تم معالجته مسبقاً');
            }

            // Check hierarchy permissions for staff users
            $rejectingUser = User::find($rejectedBy);
            if ($rejectingUser && $rejectingUser->user_type !== 'company') {
                $employee = User::find($adjustment->employee_id);
                if (!$employee || !$this->permissionService->canViewEmployeeRequests($rejectingUser, $employee)) {
                    Log::warning('LeaveAdjustmentService::rejectAdjustment - Hierarchy permission denied', [
                        'adjustment_id' => $id,
                        'rejector_id' => $rejectedBy,
                        'message' => 'Hierarchy permission denied',
                        'employee_id' => $adjustment->employee_id
                    ]);
                    throw new \Exception('ليس لديك صلاحية لرفض طلب هذا الموظف');
                }
            }

            $rejectedAdjustment = $this->leaveAdjustmentRepository->rejectAdjustment($adjustment, $rejectedBy, $reason);

            // Send rejection notification
            $this->notificationService->sendApprovalNotification(
                'leave_adjustment_settings',
                (string)$rejectedAdjustment->id,
                $companyId,
                StringStatusEnum::REJECTED->value,
                $rejectedBy  // Rejector ID
            );

            // Send email notification
            $employeeEmail = $rejectedAdjustment->employee->email ?? null;
            $employeeName = $rejectedAdjustment->employee->full_name ?? 'Employee';
            $leaveTypeName = $rejectedAdjustment->leaveType->leave_type_name ?? 'Leave Adjustment';

            if ($employeeEmail) {
                SendEmailNotificationJob::dispatch(
                    new LeaveAdjustmentRejected(
                        employeeName: $employeeName,
                        leaveType: $leaveTypeName,
                        adjustHours: $rejectedAdjustment->adjust_hours,
                        reason: $rejectedAdjustment->reason_adjustment,
                    ),
                    $employeeEmail
                );
            }

            return $rejectedAdjustment;
        });
    }

    /**
     * Update leave adjustment
     */
    public function updateAdjustment(int $id, UpdateLeaveAdjustmentDTO $dto, User $user): ?LeaveAdjustment
    {
        return DB::transaction(function () use ($id, $dto, $user) {

            // Get effective company ID
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // Find the adjustment in the company
            $adjustment = $this->leaveAdjustmentRepository->findAdjustmentInCompany($id, $effectiveCompanyId);

            if (!$adjustment) {
                Log::info('LeaveAdjustmentService::updateAdjustment - Adjustment not found', [
                    'adjustment_id' => $id,
                    'message' => 'Adjustment not found',
                    'company_id' => $effectiveCompanyId
                ]);
                throw new \Exception('التسوية غير موجودة');
            }

            // Check permissions using hierarchy check
            $isOwner = $adjustment->employee_id === $user->user_id;
            $isCompany = $user->user_type === 'company';
            $isHigherLevel = false;

            // Check hierarchy level if user is staff
            if (!$isCompany && $user->employee) {
                // Ensure adjustment employee is loaded
                $targetEmployee = User::find($adjustment->employee_id);
                // A simpler check using hierarchy level directly if designated
                $targetHierarchy = $targetEmployee->hierarchy_level ?? 999;
                $isHigherLevel = $user->hierarchy_level < $targetHierarchy;
            }

            if (!$isOwner && !$isCompany && !$isHigherLevel) {
                // Additional explicit check using permission service for consistency
                $targetEmployee = User::find($adjustment->employee_id);
                if (!$targetEmployee || !$this->permissionService->canViewEmployeeRequests($user, $targetEmployee)) {
                    Log::info('LeaveAdjustmentService::updateAdjustment - Unauthorized update attempt', [
                        'user_id' => $user->user_id,
                        'adjustment_id' => $id,
                        'message' => 'Unauthorized update attempt'
                    ]);
                    throw new \Exception('ليس لديك صلاحية لتعديل هذا الطلب');
                }
            }

            if ($adjustment->status !== LeaveAdjustment::STATUS_PENDING) {
                Log::info('LeaveAdjustmentService::updateAdjustment - Adjustment not pending', [
                    'adjustment_id' => $id,
                    'message' => 'Adjustment not pending',
                    'company_id' => $effectiveCompanyId,
                    'status' => $adjustment->status
                ]);
                throw new \Exception('لا يمكن تعديل التسوية بعد المراجعة');
            }

            $updatedAdjustment = $this->leaveAdjustmentRepository->updateAdjustment($adjustment, $dto);
            // Send email notification
            $employeeEmail = $updatedAdjustment->employee->email ?? null;
            $employeeName = $updatedAdjustment->employee->full_name ?? 'Employee';
            $leaveTypeName = $updatedAdjustment->leaveType->leave_type_name ?? 'Leave Adjustment';

            if ($employeeEmail) {
                SendEmailNotificationJob::dispatch(
                    new LeaveAdjustmentRejected(
                        employeeName: $employeeName,
                        leaveType: $leaveTypeName,
                        adjustHours: $updatedAdjustment->adjust_hours,
                        reason: $updatedAdjustment->reason_adjustment,
                    ),
                    $employeeEmail
                );
            }
            return $updatedAdjustment;
        });
    }

    /**
     * Cancel leave adjustment (mark as rejected)
     */
    public function cancelAdjustment(int $id, User $user): bool
    {
        return DB::transaction(function () use ($id, $user) {
            // Get effective company ID
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // Find adjustment in company
            $adjustment = $this->leaveAdjustmentRepository->findAdjustmentInCompany($id, $effectiveCompanyId);

            if (!$adjustment) {
                Log::info('LeaveAdjustmentService::cancelAdjustment - Adjustment not found', [
                    'adjustment_id' => $id,
                    'message' => 'Adjustment not found',
                    'company_id' => $effectiveCompanyId
                ]);
                throw new \Exception('التسوية غير موجودة أو لا تنتمي لهذه الشركة');
            }

            // 1. Status Check: Only Pending (0) can be cancelled
            if ($adjustment->status !== LeaveAdjustment::STATUS_PENDING) {
                Log::info('LeaveAdjustmentService::cancelAdjustment - Adjustment not pending', [
                    'adjustment_id' => $id,
                    'message' => 'Adjustment not pending',
                    'company_id' => $effectiveCompanyId,
                    'status' => $adjustment->status
                ]);
                throw new \Exception('لا يمكن إلغاء الطلب بعد المراجعة (موافق عليه أو مرفوض)');
            }

            // 2. Permission Check
            $isOwner = $adjustment->employee_id === $user->user_id;
            $isCompany = $user->user_type === 'company';

            // Check hierarchy permission (is a manager of the employee)
            $isHierarchyManager = false;
            if (!$isOwner && !$isCompany) {
                $employee = User::find($adjustment->employee_id);
                if ($employee && $this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    $isHierarchyManager = true;
                }
            }

            if (!$isOwner && !$isCompany && !$isHierarchyManager) {
                Log::info('LeaveAdjustmentService::cancelAdjustment - Unauthorized cancellation attempt', [
                    'user_id' => $user->user_id,
                    'adjustment_id' => $id,
                    'message' => 'Unauthorized cancellation attempt'
                ]);
                throw new \Exception('ليس لديك صلاحية لإلغاء هذا الطلب');
            }

            // Determine cancel reason based on who is cancelling
            $cancelReason = $isOwner
                ? 'تم إلغاء التسوية من قبل ' . $user->full_name
                : 'تم إلغاء التسوية من قبل الإدارة';

            // Mark as rejected (keeps record in database)
            $cancelledAdjustment = $this->leaveAdjustmentRepository->cancelAdjustment($adjustment, $user->user_id, $cancelReason);

            // Send rejection notification (Self-rejection/Cancellation)
            $this->notificationService->sendApprovalNotification(
                'leave_adjustment_settings',
                (string)$cancelledAdjustment->id,
                $cancelledAdjustment->company_id,
                StringStatusEnum::REJECTED->value,
                $user->user_id
            );

            // Send email notification
            $employeeEmail = $cancelledAdjustment->employee->email ?? null;
            $employeeName = $cancelledAdjustment->employee->full_name ?? 'Employee';
            $leaveTypeName = $cancelledAdjustment->leaveType->leave_type_name ?? 'Leave Adjustment';

            if ($employeeEmail) {
                SendEmailNotificationJob::dispatch(
                    new LeaveAdjustmentRejected(
                        employeeName: $employeeName,
                        leaveType: $leaveTypeName,
                        adjustHours: $cancelledAdjustment->adjust_hours,
                        reason: $cancelledAdjustment->reason_adjustment,
                    ),
                    $employeeEmail
                );
            }
            return true;
        });
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
        return $this->leaveService->getAvailableLeaveBalance($employeeId, $leaveTypeId, $companyId);
    }

    /**
     * Get leave adjustment by ID with company validation
     */
    public function showLeaveAdjustment(int $id, int $effectiveCompanyId, ?User $user = null): ?LeaveAdjustment
    {
        $adjustment = $this->leaveAdjustmentRepository->findAdjustmentInCompany($id, $effectiveCompanyId);

        if (!$adjustment) {
            Log::info('LeaveAdjustmentService::showLeaveAdjustment - Adjustment not found', [
                'adjustment_id' => $id,
                'message' => 'Adjustment not found',
                'company_id' => $effectiveCompanyId
            ]);
            throw new \Exception('تسوية الإجازة غير موجودة أو لا تنتمي إلى هذه الشركة');
        }

        if ($user) {
            // Company users can see all applications in their company
            if ($user->user_type === 'company') {
                // Already filtered by findAdjustmentInCompany with effective ID
            } else {
                // Check ownership
                if ($adjustment->employee_id !== $user->user_id) {
                    // Check hierarchy
                    $employee = User::find($adjustment->employee_id);
                    if (!$employee || !$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                        Log::info('LeaveAdjustmentService::showLeaveAdjustment - Unauthorized view attempt', [
                            'user_id' => $user->user_id,
                            'adjustment_id' => $id,
                            'message' => 'Unauthorized view attempt'
                        ]);
                        throw new \Exception('ليس لديك صلاحية لعرض تفاصيل هذه التسوية');
                    }
                }
            }
        }

        return $adjustment;
    }


    /**
     * الحصول على قوائم Enums الخاصة بالإجازات
     * 
     * @return array
     */
    public function getLeaveEnums(): array
    {
        $user = Auth::user();

        // استخدام getEffectiveCompanyId دائماً
        $companyId = $this->permissionService->getEffectiveCompanyId($user);

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
            'statuses_numeric' => \App\Enums\NumericalStatusEnum::toArray(),
            'leave_types' => $leavetypes
        ];
    }

    /**
     * الحصول على أنواع الإجازات المحظورة للمستخدم
     */
    protected function getRestrictedLeaveTypeIds(User $user, int $companyId): array
    {
        $restrictedIds = [];
        if ($user->user_type !== 'company') {
            $restriction = \App\Models\OperationRestriction::where('user_id', $user->user_id)
                ->where('company_id', $companyId)
                ->first();

            if ($restriction) {
                $restrictedOperations = $restriction->restricted_operations;
                foreach ($restrictedOperations as $operation) {
                    if (preg_match('/^leave_type_(\d+)$/', $operation, $matches)) {
                        $restrictedIds[] = (int) $matches[1];
                    }
                }
            }
        }
        return $restrictedIds;
    }
}
