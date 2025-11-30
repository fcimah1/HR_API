<?php

namespace App\Services;

use App\DTOs\LeaveAdjustment\LeaveAdjustmentFilterDTO;
use App\DTOs\LeaveAdjustment\CreateLeaveAdjustmentDTO;
use App\DTOs\LeaveAdjustment\UpdateLeaveAdjustmentDTO;
use App\Models\LeaveAdjustment;
use App\Models\User;
use App\Repository\Interface\LeaveAdjustmentRepositoryInterface;
use App\Services\SimplePermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\LeaveAdjustment\AdjustmentSubmitted;
use App\Mail\LeaveAdjustment\AdjustmentApproved;
use App\Mail\LeaveAdjustment\AdjustmentRejected;
use App\Enums\StringStatusEnum;

class LeaveAdjustmentService
{
    protected $permissionService;
    protected $leaveService;
    protected $leaveAdjustmentRepository;
    protected $notificationService;

    public function __construct(
        SimplePermissionService $permissionService,
        LeaveService $leaveService,
        LeaveAdjustmentRepositoryInterface $leaveAdjustmentRepository,
        NotificationService $notificationService
    ) {
        $this->permissionService = $permissionService;
        $this->leaveService = $leaveService;
        $this->leaveAdjustmentRepository = $leaveAdjustmentRepository;
        $this->notificationService = $notificationService;
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
        $filters = LeaveAdjustmentFilterDTO::fromRequest($filterData, $user);

        $adjustments = $this->leaveAdjustmentRepository->getPaginatedAdjustments($filters, $user);

        return [
            'created_by' => $user->full_name,
            'company_id' => $user->company_id,
            'data' => $adjustments->items(),
            'pagination' => [
                'total' => $adjustments->total(),
                'per_page' => $adjustments->perPage(),
                'current_page' => $adjustments->currentPage(),
                'last_page' => $adjustments->lastPage(),
            ]
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
        // إذا كان موظف عادي، يرى طلباته الشخصية فقط
        else {
            $filterData['employee_id'] = $user->user_id;
            $filterData['company_id'] = $user->company_id;
        }

        $updatedFilters = LeaveAdjustmentFilterDTO::fromRequest($filterData, $user);

        $adjustments = $this->leaveAdjustmentRepository->getPaginatedAdjustments($updatedFilters);

        return [
            'data' => $adjustments,
            'created by' => $user->full_name,
            'pagination' => [
                'current_page' => $adjustments->currentPage(),
                'last_page' => $adjustments->lastPage(),
                'per_page' => $adjustments->perPage(),
                'total' => $adjustments->total(),
                'from' => $adjustments->firstItem(),
                'to' => $adjustments->lastItem(),
                'has_more_pages' => $adjustments->hasMorePages(),
            ]
        ];
    }
    /**
     * Create leave adjustment
     */
    public function createAdjust(CreateLeaveAdjustmentDTO $data): array
    {
        return DB::transaction(function () use ($data) {
            Log::info('LeaveAdjustmentService::createAdjustment - Transaction started', [
                'employee_id' => $data->employeeId,
                'leave_type_id' => $data->leaveTypeId,
                'adjust_hours' => $data->adjustHours
            ]);

            // إذا كانت التسوية خصم من رصيد الإجازات (ساعات سالبة)، تحقق من أن الرصيد يكفي
            if ($data->adjustHours < 0) {
                $availableBalance = $this->leaveService->getAvailableLeaveBalance(
                    $data->employeeId,
                    $data->leaveTypeId,
                    $data->companyId
                );

                $hoursToDeduct = abs($data->adjustHours);

                if ($availableBalance < $hoursToDeduct) {
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
                Mail::to($employeeEmail)->send(new AdjustmentSubmitted(
                    employeeName: $employeeName,
                    leaveType: $leaveTypeName,
                    adjustHours: $data->adjustHours,
                    reason: $data->reasonAdjustment
                ));
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
                throw new \Exception('تسوية الإجازة غير موجودة أو لا تنتمي إلى هذه الشركة');
            }

            if ($adjustment->status !== LeaveAdjustment::STATUS_PENDING) {
                throw new \Exception('لا يمكن الموافقة على هذا الطلب لأنه تم معالجته مسبقاً');
            }

            $approvedAdjustment = $this->leaveAdjustmentRepository->approveAdjustment($adjustment, $approvedBy);

            // Send approval notification
            $this->notificationService->sendApprovalNotification(
                'leave_adjustment_settings',
                (string)$approvedAdjustment->id,
                $companyId,
                StringStatusEnum::APPROVED->value
            );

            // Send email notification
            $employeeEmail = $approvedAdjustment->employee->email ?? null;
            $employeeName = $approvedAdjustment->employee->full_name ?? 'Employee';
            $leaveTypeName = $approvedAdjustment->leaveType->leave_type_name ?? 'Leave Adjustment';

            if ($employeeEmail) {
                Mail::to($employeeEmail)->send(new AdjustmentApproved(
                    employeeName: $employeeName,
                    leaveType: $leaveTypeName,
                    adjustHours: $approvedAdjustment->adjust_hours,
                    remarks: null
                ));
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
                throw new \Exception('تسوية الإجازة غير موجودة أو لا تنتمي إلى هذه الشركة');
            }

            if ($adjustment->status !== LeaveAdjustment::STATUS_PENDING) {
                throw new \Exception('لا يمكن رفض هذا الطلب لأنه تم معالجته مسبقاً');
            }

            $rejectedAdjustment = $this->leaveAdjustmentRepository->rejectAdjustment($adjustment, $rejectedBy, $reason);

            // Send rejection notification
            $this->notificationService->sendApprovalNotification(
                'leave_adjustment_settings',
                (string)$rejectedAdjustment->id,
                $companyId,
                StringStatusEnum::REJECTED->value
            );

            // Send email notification
            $employeeEmail = $rejectedAdjustment->employee->email ?? null;
            $employeeName = $rejectedAdjustment->employee->full_name ?? 'Employee';
            $leaveTypeName = $rejectedAdjustment->leaveType->leave_type_name ?? 'Leave Adjustment';

            if ($employeeEmail) {
                Mail::to($employeeEmail)->send(new AdjustmentRejected(
                    employeeName: $employeeName,
                    leaveType: $leaveTypeName,
                    adjustHours: $rejectedAdjustment->adjust_hours,
                    reason: $reason
                ));
            }

            return $rejectedAdjustment;
        });
    }

    /**
     * Update leave adjustment
     */
    /**
     * Update leave adjustment
     */
    public function updateAdjustment(int $id, UpdateLeaveAdjustmentDTO $dto, int $employeeId): ?LeaveAdjustment
    {
        return DB::transaction(function () use ($id, $dto, $employeeId) {
            $adjustment = $this->leaveAdjustmentRepository->findAdjustmentForEmployee($id, $employeeId);

            if (!$adjustment) {
                throw new \Exception('التسوية غير موجودة');
            }

            if ($adjustment->status !== LeaveAdjustment::STATUS_PENDING) {
                throw new \Exception('لا يمكن تعديل التسوية بعد المراجعة');
            }

            $updatedAdjustment = $this->leaveAdjustmentRepository->updateAdjustment($adjustment, $dto);
            // Send email notification
            $employeeEmail = $updatedAdjustment->employee->email ?? null;
            $employeeName = $updatedAdjustment->employee->full_name ?? 'Employee';
            $leaveTypeName = $updatedAdjustment->leaveType->leave_type_name ?? 'Leave Adjustment';

            if ($employeeEmail) {
                Mail::to($employeeEmail)->send(new AdjustmentRejected(
                    employeeName: $employeeName,
                    leaveType: $leaveTypeName,
                    adjustHours: $updatedAdjustment->adjust_hours,
                    reason: $updatedAdjustment->reason,
                ));
            }
            return $updatedAdjustment;
        });
    }

    /**
     * Cancel leave adjustment (mark as rejected)
     */
    public function cancelAdjustment(int $id, int $employeeId): bool
    {
        return DB::transaction(function () use ($id, $employeeId) {
            $adjustment = $this->leaveAdjustmentRepository->findAdjustmentForEmployee($id, $employeeId);

            if (!$adjustment) {
                throw new \Exception('التسوية غير موجودة');
            }

            if ($adjustment->status !== LeaveAdjustment::STATUS_PENDING) {
                throw new \Exception('لا يمكن إلغاء التسوية بعد المراجعة');
            }

            // Mark as rejected (keeps record in database)
            $cancelledAdjustment = $this->leaveAdjustmentRepository->cancelAdjustment($adjustment, $employeeId, 'تم إلغاء التسوية من قبل الموظف');

                        // Send rejection notification
            $this->notificationService->sendApprovalNotification(
                'leave_adjustment_settings',
                (string)$cancelledAdjustment->id,
                $cancelledAdjustment->company_id,
                StringStatusEnum::REJECTED->value
            );
            // Send email notification
            $employeeEmail = $cancelledAdjustment->employee->email ?? null;
            $employeeName = $cancelledAdjustment->employee->full_name ?? 'Employee';
            $leaveTypeName = $cancelledAdjustment->leaveType->leave_type_name ?? 'Leave Adjustment';

            if ($employeeEmail) {
                Mail::to($employeeEmail)->send(new AdjustmentRejected(
                    employeeName: $employeeName,
                    leaveType: $leaveTypeName,
                    adjustHours: $cancelledAdjustment->adjust_hours,
                    reason: $cancelledAdjustment->reason,
                ));
            }
            return true;
        });
    }

    /**
     * Get leave adjustment by ID with company validation
     */
    public function showLeaveAdjustment(int $id, int $companyId): ?LeaveAdjustment
    {
        $adjustment = $this->leaveAdjustmentRepository->findAdjustmentInCompany($id, $companyId);

        if (!$adjustment) {
            throw new \Exception('تسوية الإجازة غير موجودة أو لا تنتمي إلى هذه الشركة');
        }

        return $adjustment;
    }

    // /**
    //  * Get available leave balance for an employee
    //  *
    //  * @param int $employeeId
    //  * @param int $leaveTypeId
    //  * @param int $companyId
    //  * @return float
    //  */
    // public function handleLeaveSettlement(CreateLeaveSettlementDTO $dto): array
    // {
    //     DB::beginTransaction();
    //     try {
    //         // 1. Check available balance
    //         $availableBalance = $this->getAvailableLeaveBalance(
    //             $dto->employeeId,
    //             $dto->leaveTypeId,
    //             $dto->companyId
    //         );

    //         if ($availableBalance < $dto->hoursToSettle) {
    //             throw new \Exception('الرصيد المتاح (' . $availableBalance . ' ساعة) غير كافٍ لتسوية ' . $dto->hoursToSettle . ' ساعة.');
    //         }

    //         // 2. Perform the settlement based on type
    //         if ($dto->settlementType === 'encashment') {
    //             // Logic for Encashment (Cash out)
    //             // This typically involves:
    //             // a) Creating a Leave Adjustment to deduct the settled hours.
    //             // b) Creating a record in a payroll/finance system for payment (Not implemented here, only the HR side).

    //             $adjustmentData = new CreateLeaveAdjustmentDTO(
    //                 employeeId: $dto->employeeId,
    //                 leaveTypeId: $dto->leaveTypeId,
    //                 adjustHours: -$dto->hoursToSettle, // Negative value to deduct from balance
    //                 reasonAdjustment: 'تسوية إجازات مستحقة (صرف نقدي) - ' . $dto->hoursToSettle . ' ساعة',
    //                 adjustmentDate: now()->toDateString(),
    //                 dutyEmployeeId: null,
    //                 companyId: $dto->companyId,
    //             );

    //             $adjustment = $this->leaveAdjustmentRepository->createAdjust($adjustmentData);

    //             $message = 'تمت تسوية ' . $dto->hoursToSettle . ' ساعة بنجاح كصرف نقدي. تم تحديث رصيد الإجازات.';
    //             $settlementRecord = $adjustment->toArray();

    //         } elseif ($dto->settlementType === 'take_leave') {
    //             // Logic for Taking Leave (Converting to a leave application)
    //             // This is essentially creating a new leave application with the settled hours.

    //             // Note: This assumes 'hoursToSettle' is the total duration of the leave application.
    //             // If the user meant converting the *remaining balance* into a single application, 
    //             // the logic should be adjusted to use the full available balance.

    //             // For simplicity, we will create a Leave Adjustment to deduct the hours, 
    //             // and the system should handle the actual leave application separately.
    //             // However, based on the user's request "تسوية اجازاته المستحقه", 
    //             // we will treat 'take_leave' as a final deduction/settlement.

    //             $adjustmentData = new CreateLeaveAdjustmentDTO(
    //                 companyId: $dto->companyId,
    //                 employeeId: $dto->employeeId,
    //                 leaveTypeId: $dto->leaveTypeId,
    //                 adjustHours: -$dto->hoursToSettle, // Negative value to deduct from balance
    //                 reasonAdjustment: 'تسوية إجازات مستحقة (أخذ إجازة) - ' . $dto->hoursToSettle . ' ساعة',
    //                 adjustmentDate: now()->toDateString(),
    //                 dutyEmployeeId: null,
    //                 status: 1 // Approved immediately for settlement
    //             );

    //             $adjustment = $this->leaveAdjustmentRepository->createAdjust($adjustmentData);

    //             $message = 'تمت تسوية ' . $dto->hoursToSettle . ' ساعة بنجاح كإجازة مأخوذة. تم تحديث رصيد الإجازات.';
    //             $settlementRecord = $adjustment->toArray();
    //         } else {
    //             throw new \InvalidArgumentException('نوع التسوية غير صالح.');
    //         }

    //         // 3. Recalculate new balance
    //         $newAvailableBalance = $this->getAvailableLeaveBalance(
    //             $dto->employeeId,
    //             $dto->leaveTypeId,
    //             $dto->companyId
    //         );

    //         DB::commit();

    //         return [
    //             'success' => true,
    //             'message' => $message,
    //             'settlement_type' => $dto->settlementType,
    //             'hours_settled' => $dto->hoursToSettle,
    //             'old_balance' => $availableBalance,
    //             'new_balance' => $newAvailableBalance,
    //             'record' => $settlementRecord
    //         ];

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::error('LeaveService::handleLeaveSettlement failed', [
    //             'error' => $e->getMessage(),
    //             'employee_id' => $dto->employeeId
    //         ]);
    //         throw $e;
    //     }
    // }



}
