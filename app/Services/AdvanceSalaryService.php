<?php

namespace App\Services;

use App\Repository\Interface\AdvanceSalaryRepositoryInterface;
use App\DTOs\AdvanceSalary\AdvanceSalaryFilterDTO;
use App\DTOs\AdvanceSalary\CreateAdvanceSalaryDTO;
use App\DTOs\AdvanceSalary\UpdateAdvanceSalaryDTO;
use App\DTOs\AdvanceSalary\AdvanceSalaryResponseDTO;
use App\Models\User;
use App\Services\SimplePermissionService;
use App\Enums\StringStatusEnum;
use App\Jobs\SendEmailNotificationJob;
use App\Mail\AdvanceSalary\AdvanceSalarySubmitted;
use App\Mail\AdvanceSalary\AdvanceSalaryApproved;
use App\Mail\AdvanceSalary\AdvanceSalaryRejected;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdvanceSalaryService
{
    public function __construct(
        private readonly AdvanceSalaryRepositoryInterface $advanceSalaryRepository,
        private readonly SimplePermissionService $permissionService,
        private readonly NotificationService $notificationService,
        private readonly \App\Repository\Interface\UserRepositoryInterface $userRepository
    ) {}

    /**
     * Get paginated advance salary/loan requests with filters and permission check
     */
    public function getPaginatedAdvances(AdvanceSalaryFilterDTO $filters, User $user): array
    {
        // Create new filters based on user permissions
        $filterData = $filters->toArray();

        // التحقق من نوع المستخدم (company أو staff فقط)
        if ($user->user_type == 'company') {
            // مدير الشركة: يرى جميع طلبات شركته
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $filterData['company_id'] = $effectiveCompanyId;
        } else {
            // موظف (staff): يرى طلباته + طلبات الموظفين التابعين له في نفس القسم
            $subordinateIds = $this->userRepository->getSubordinateEmployeeIds($user->user_id);

            if (!empty($subordinateIds)) {
                // لديه موظفين تابعين: طلباته + طلبات التابعين
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

        // Create new DTO with updated data
        $updatedFilters = AdvanceSalaryFilterDTO::fromRequest($filterData);

        $advances = $this->advanceSalaryRepository->getPaginatedAdvances($updatedFilters);

        $advanceDTOs = collect($advances->items())->map(function ($advance) {
            return AdvanceSalaryResponseDTO::fromModel($advance);
        });

        return [
            'data' => $advanceDTOs->map(fn($dto) => $dto->toArray())->toArray(),
            'pagination' => [
                'current_page' => $advances->currentPage(),
                'last_page' => $advances->lastPage(),
                'per_page' => $advances->perPage(),
                'total' => $advances->total(),
                'from' => $advances->firstItem() ?? 0,
                'to' => $advances->lastItem() ?? 0,
                'has_more_pages' => $advances->hasMorePages(),
            ]
        ];
    }

    /**
     * Create a new advance salary/loan request with permission check
     */
    public function createAdvance(CreateAdvanceSalaryDTO $dto): AdvanceSalaryResponseDTO
    {
        return DB::transaction(function () use ($dto) {
            try {
                Log::info('AdvanceSalaryService::createAdvance started', [
                    'company_id' => $dto->companyId,
                    'employee_id' => $dto->employeeId,
                    'salary_type' => $dto->salaryType,
                    'amount' => $dto->advanceAmount,
                    'month_year' => $dto->monthYear
                ]);

                // check if the employee has any loan or advance salary to use it create new loan or advance salary
                if ($this->advanceSalaryRepository->findApprovedAdvanceInCompany($dto->employeeId, $dto->companyId)) {
                    throw new \Exception('الموظف لديه طلب قرض/سلف مسبق تم الموافقة عليه ولم يتم الانتهاء من دفعه');
                } else if ($this->advanceSalaryRepository->findPendingAdvanceInCompany($dto->employeeId, $dto->companyId)) {
                    throw new \Exception('الموظف لديه طلب قرض/سلف مسبق في انتظار الموافقة');
                } else {
                    $advance = $this->advanceSalaryRepository->createAdvance($dto);
                }

                // Send submission notification
                $this->notificationService->sendSubmissionNotification(
                    'advance_salary_settings',
                    (string)$advance->advance_salary_id,
                    $dto->companyId,
                    StringStatusEnum::SUBMITTED->value,
                    $dto->employeeId
                );

                // Send email notification
                $employeeEmail = $advance->employee->email ?? null;
                $employeeName = $advance->employee->full_name ?? 'Employee';

                if ($employeeEmail) {
                    SendEmailNotificationJob::dispatch(
                        new AdvanceSalarySubmitted(
                            employeeName: $employeeName,
                            amount: (float)$advance->advance_amount,
                            salaryType: $advance->salary_type
                        ),
                        $employeeEmail
                    );
                }

                Log::info('AdvanceSalaryService::createAdvance completed successfully', [
                    'advance_id' => $advance->advance_salary_id,
                    'salary_type' => $advance->salary_type,
                    'amount' => $advance->advance_amount
                ]);

                return AdvanceSalaryResponseDTO::fromModel($advance);
            } catch (\Exception $e) {
                Log::error('AdvanceSalaryService::createAdvance failed', [
                    'error' => $e->getMessage(),
                    'company_id' => $dto->companyId,
                    'employee_id' => $dto->employeeId,
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Get advance salary/loan by ID with permission check
     * 
     * @param int $id Advance ID
     * @param int|null $companyId Company ID (for company users/admins)
     * @param int|null $userId User ID (for regular employees)
     * @param User|null $user User object
     * @return array|null Returns ['advance' => AdvanceSalaryResponseDTO|null, 'reason' => string|null]
     * @throws \Exception
     */
    public function getAdvanceById(int $id, ?int $companyId = null, ?int $userId = null, ?User $user = null): ?array
    {
        $user = $user ?? User::find($userId);

        if (is_null($companyId) && is_null($userId) && is_null($user)) {
            Log::error('AdvanceSalaryService::getAdvanceById - Invalid arguments', [
                'advance_id' => $id
            ]);
            throw new \InvalidArgumentException('يجب توفير معرف الشركة أو معرف المستخدم أو كائن المستخدم');
        }

        $advance = null;
        $exists = false;

        // Company users can see all applications in their company
        if ($user && $user->user_type === 'company') {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $advance = $this->advanceSalaryRepository->findAdvanceInCompany($id, $effectiveCompanyId);
            $exists = $advance !== null;

            if ($advance) {
                Log::info('AdvanceSalaryService::getAdvanceById - Found by company', [
                    'advance_id' => $id,
                    'company_id' => $effectiveCompanyId
                ]);
                return [
                    'advance' => AdvanceSalaryResponseDTO::fromModel($advance),
                    'reason' => null
                ];
            }
        }
        // Staff users: check hierarchy permissions
        else {
            // First, try to find by user ID (own requests) - but only if this is actually the user's own request
            if ($userId !== null) {
                try {
                    $advance = $this->advanceSalaryRepository->findAdvanceForEmployee($id, $userId);
                    $exists = $advance !== null;

                    if ($advance) {
                        Log::info('AdvanceSalaryService::getAdvanceById - Found by employee', [
                            'advance_id' => $id,
                            'user_id' => $userId
                        ]);
                        return [
                            'advance' => AdvanceSalaryResponseDTO::fromModel($advance),
                            'reason' => null
                        ];
                    }
                } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                    // This is not the user's own request, continue to check hierarchy permissions
                    Log::info('AdvanceSalaryService::getAdvanceById - Not user own request, checking hierarchy', [
                        'advance_id' => $id,
                        'user_id' => $userId
                    ]);
                }
            }

            // Then, try to find in company and check hierarchy permissions
            if ($user) {
                $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
                $advance = $this->advanceSalaryRepository->findAdvanceInCompany($id, $effectiveCompanyId);
                $exists = $advance !== null;

                if ($advance) {
                    // Check if user can view this employee's requests based on hierarchy
                    $employee = User::find($advance->employee_id);
                    if ($employee) {
                        $canView = $this->permissionService->canViewEmployeeRequests($user, $employee);

                        Log::info('AdvanceSalaryService::getAdvanceById - Hierarchy check', [
                            'advance_id' => $id,
                            'advance_employee_id' => $advance->employee_id,
                            'requester_id' => $user->user_id,
                            'requester_type' => $user->user_type,
                            'requester_level' => $this->permissionService->getUserHierarchyLevel($user),
                            'employee_level' => $this->permissionService->getUserHierarchyLevel($employee),
                            'requester_department' => $this->permissionService->getUserDepartmentId($user),
                            'employee_department' => $this->permissionService->getUserDepartmentId($employee),
                            'can_view' => $canView
                        ]);

                        if ($canView) {
                            return [
                                'advance' => AdvanceSalaryResponseDTO::fromModel($advance),
                                'reason' => null
                            ];
                        } else {
                            return [
                                'advance' => null,
                                'reason' => 'ليس لديك صلاحية لعرض هذا الطلب'
                            ];
                        }
                    }
                }
            }
        }

        Log::warning('AdvanceSalaryService::getAdvanceById - Not found', [
            'advance_id' => $id,
            'company_id' => $companyId,
            'user_id' => $userId
        ]);

        return [
            'advance' => null,
            'reason' => $exists ? 'ليس لديك صلاحية لعرض هذا الطلب' : 'الطلب غير موجود'
        ];
    }

    /**
     * Update advance salary/loan request with permission check
     */
    public function updateAdvance(int $id, UpdateAdvanceSalaryDTO $dto, User $user): ?AdvanceSalaryResponseDTO
    {
        DB::beginTransaction();
        try {
            Log::info('AdvanceSalaryService::updateAdvance started', [
                'advance_id' => $id,
                'user_id' => $user->user_id,
                'updates' => array_keys(array_filter($dto->toArray()))
            ]);

            // Get effective company ID first
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // Find advance without loading relationships first
            $advance = $this->advanceSalaryRepository->findAdvanceInCompany($id, $effectiveCompanyId);

            if (!$advance) {
                Log::warning('Advance not found', ['advance_id' => $id, 'company_id' => $effectiveCompanyId]);
                DB::rollBack();
                return null;
            }

            // Check permissions
            $isOwner = $advance->employee_id === $user->user_id;
            $isCompany = $user->user_type === 'company';
            $isHigherLevel = false;

            // Check hierarchy level if user is staff
            if (!$isCompany && $user->employee) {
                // Ensure advance employee is loaded
                $targetEmployee = User::find($advance->employee_id);
                // A simpler check using hierarchy level directly if designated
                $targetHierarchy = $targetEmployee->hierarchy_level ?? 999;

                // Manager must have a smaller hierarchy number (higher rank) than the employee
                $isHigherLevel = $user->hierarchy_level < $targetHierarchy;
            }

            if (!$isOwner && !$isCompany && !$isHigherLevel) {
                // Additional explicit check using permission service for consistency
                $targetEmployee = User::find($advance->employee_id);
                if (!$targetEmployee || !$this->permissionService->canViewEmployeeRequests($user, $targetEmployee)) {
                    Log::error('Unauthorized update attempt', [
                        'user_id' => $user->user_id,
                        'advance_id' => $id,
                        'is_owner' => $isOwner,
                        'is_company' => $isCompany,
                        'is_higher_level' => $isHigherLevel
                    ]);
                    DB::rollBack();
                    throw new \Exception('ليس لديك صلاحية لتعديل هذا الطلب');
                }
            }

            // Check if advance can be updated (only pending)
            if ($advance->status !== 0) {
                Log::warning('Cannot update - not pending', ['status' => $advance->status]);
                DB::rollBack();
                throw new \Exception('لا يمكن تعديل الطلب بعد المراجعة');
            }

            // Safeguard: If we are updating to one_time_deduct = 1, force monthly_installment to equal advance_amount
            // This handles cases where client might only send one_time_deduct=1 without amount or installment
            $newOneTimeDeduct = $dto->oneTimeDeduct;
            $updates = $dto->toArray();

            // If one_time_deduct is being updated to TRUE (1)
            // Or if it is not being updated, but already IS TRUE in DB, and we are updating amount
            $isOneTime = ($newOneTimeDeduct !== null && $newOneTimeDeduct == '1') ||
                ($newOneTimeDeduct === null && $advance->one_time_deduct == '1');

            if ($isOneTime) {
                // Determine the relevant advance amount (new from DTO or existing from DB)
                $targetAmount = $dto->advanceAmount ?? $advance->advance_amount;

                // If monthly_installment is missing or different in DTO, force it in the update
                // Since DTO is readonly, we can'to modify it. But Repository takes the DTO.
                // We actually need to modify the data passed to repository OR handle it in Repository.
                // However, Repository expects DTO.
                // Let's create a NEW DTO with the forced value if needed.

                if ($dto->monthlyInstallment === null || $dto->monthlyInstallment != $targetAmount) {
                    // Re-create DTO with correct installment
                    $dto = new UpdateAdvanceSalaryDTO(
                        monthYear: $dto->monthYear,
                        advanceAmount: $dto->advanceAmount,
                        oneTimeDeduct: $dto->oneTimeDeduct,
                        monthlyInstallment: $targetAmount, // FORCE EQUALITY
                        reason: $dto->reason
                    );
                }
            }

            // Update advance
            $updatedAdvance = $this->advanceSalaryRepository->updateAdvance($advance, $dto);

            DB::commit();

            Log::info('Advance updated successfully', [
                'advance_id' => $updatedAdvance->advance_salary_id,
                'updates' => array_keys(array_filter($dto->toArray()))
            ]);

            return AdvanceSalaryResponseDTO::fromModel($updatedAdvance);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in AdvanceSalaryService::updateAdvance', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Cancel advance salary/loan request (mark as rejected/cancelled)
     */
    public function cancelAdvance(int $id, User $user): bool
    {
        return DB::transaction(function () use ($id, $user) {
            try {
                Log::info('AdvanceSalaryService::cancelAdvance started', [
                    'advance_id' => $id,
                    'user_id' => $user->user_id,
                    'user_type' => $user->user_type
                ]);

                // Get effective company ID
                $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

                // Find advance in same company
                $advance = $this->advanceSalaryRepository->findAdvanceInCompany($id, $effectiveCompanyId);

                if (!$advance) {
                    Log::warning('AdvanceSalaryService::cancelAdvance - Advance not found', [
                        'advance_id' => $id,
                        'company_id' => $effectiveCompanyId
                    ]);
                    return false;
                }

                // Check permissions:
                // 1. Employee owner can cancel their own pending requests only
                // 2. Manager/Company can cancel any request (pending or approved)
                $isOwner = $advance->employee_id === $user->user_id;
                $isCompany = $user->user_type === 'company';

                // Check if user has hierarchy permission (is a manager of the employee)
                $isHierarchyManager = false;
                if (!$isOwner && !$isCompany) {
                    $employee = User::find($advance->employee_id);
                    if ($employee && $this->permissionService->canViewEmployeeRequests($user, $employee)) {
                        $isHierarchyManager = true;
                    }
                }

                if (!$isOwner && !$isCompany && !$isHierarchyManager) {
                    Log::warning('AdvanceSalaryService::cancelAdvance - Permission denied', [
                        'advance_id' => $id,
                        'user_id' => $user->user_id,
                        'advance_employee_id' => $advance->employee_id
                    ]);
                    throw new \Exception('ليس لديك صلاحية لإلغاء هذا الطلب');
                }

                // Regular employee (owner) can only cancel pending requests
                // Managers/Company can cancel approved/processed requests

                if ($isOwner && !$isCompany && !$isHierarchyManager && $advance->status !== 0) {
                    Log::warning('AdvanceSalaryService::cancelAdvance - Cannot cancel non-pending request', [
                        'advance_id' => $id,
                        'status' => $advance->status
                    ]);
                    throw new \Exception('لا يمكن إلغاء الطلب بعد المراجعة');
                }

                // Recalculate reason based on who is cancelling
                $cancelReason = ($isCompany || $isHierarchyManager) ? 'تم إلغاء الطلب من قبل الإدارة' : 'تم إلغاء الطلب من قبل الموظف';
                $this->advanceSalaryRepository->rejectAdvance($advance, $user->user_id, $cancelReason);

                Log::info('AdvanceSalaryService::cancelAdvance completed successfully', [
                    'advance_id' => $id,
                    'cancelled_by' => $user->user_id,
                    'is_manager' => $isHierarchyManager || $isCompany,
                    'cancel_reason' => $cancelReason
                ]);

                return true;
            } catch (\Exception $e) {
                Log::error('AdvanceSalaryService::cancelAdvance failed', [
                    'advance_id' => $id,
                    'user_id' => $user->user_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Approve advance salary/loan request
     */
    public function approveAdvance(int $id, int $companyId, int $approvedBy, ?string $remarks = null): ?AdvanceSalaryResponseDTO
    {
        return DB::transaction(function () use ($id, $companyId, $approvedBy, $remarks) {
            try {
                Log::info('AdvanceSalaryService::approveAdvance started', [
                    'advance_id' => $id,
                    'company_id' => $companyId,
                    'approved_by' => $approvedBy,
                    'has_remarks' => !empty($remarks)
                ]);

                $advance = $this->advanceSalaryRepository->findAdvanceInCompany($id, $companyId);

                if (!$advance) {
                    Log::warning('AdvanceSalaryService::approveAdvance - Advance not found', [
                        'advance_id' => $id,
                        'company_id' => $companyId
                    ]);
                    return null;
                }

                if ($advance->status !== 0) {
                    Log::warning('AdvanceSalaryService::approveAdvance - Cannot approve non-pending request', [
                        'advance_id' => $id,
                        'current_status' => $advance->status
                    ]);
                    throw new \Exception('تم الموافقة على هذا الطلب مسبقاً أو تم رفضه');
                }

                // Check hierarchy permissions for staff users
                $approvingUser = User::find($approvedBy);
                if ($approvingUser && $approvingUser->user_type !== 'company') {
                    $employee = User::find($advance->employee_id);
                    $hasPermission = false;

                    // Check hierarchy level strict
                    if ($approvingUser->employee) {
                        $targetHierarchy = $employee->hierarchy_level ?? 999;
                        if ($approvingUser->hierarchy_level < $targetHierarchy) {
                            $hasPermission = true;
                        }
                    }

                    // Fallback or additional check
                    if (!$hasPermission && (!$employee || !$this->permissionService->canViewEmployeeRequests($approvingUser, $employee))) {
                        Log::warning('AdvanceSalaryService::approveAdvance - Hierarchy permission denied', [
                            'advance_id' => $id,
                            'approver_id' => $approvedBy,
                            'employee_id' => $advance->employee_id
                        ]);
                        throw new \Exception('ليس لديك صلاحية للموافقة على طلب هذا الموظف');
                    }
                }

                $approvedAdvance = $this->advanceSalaryRepository->approveAdvance($advance, $approvedBy, $remarks);

                // Send approval notification
                $this->notificationService->sendApprovalNotification(
                    'advance_salary_settings',
                    (string)$approvedAdvance->advance_salary_id,
                    $companyId,
                    StringStatusEnum::APPROVED->value,
                    $approvedBy,
                    null,
                    $advance->employee_id
                );

                // Send email notification
                $employeeEmail = $advance->employee->email ?? null;
                $employeeName = $advance->employee->full_name ?? 'Employee';

                if ($employeeEmail) {
                    SendEmailNotificationJob::dispatch(
                        new AdvanceSalaryApproved(
                            employeeName: $employeeName,
                            amount: (float)$advance->advance_amount,
                            salaryType: $advance->salary_type,
                            remarks: $remarks
                        ),
                        $employeeEmail
                    );
                }

                Log::info('AdvanceSalaryService::approveAdvance completed successfully', [
                    'advance_id' => $id,
                    'employee_id' => $advance->employee_id,
                    'amount' => $advance->advance_amount,
                    'salary_type' => $advance->salary_type,
                    'approved_by' => $approvedBy
                ]);

                return AdvanceSalaryResponseDTO::fromModel($approvedAdvance);
            } catch (\Exception $e) {
                Log::error('AdvanceSalaryService::approveAdvance failed', [
                    'advance_id' => $id,
                    'company_id' => $companyId,
                    'approved_by' => $approvedBy,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Reject advance salary/loan request
     */
    public function rejectAdvance(int $id, int $companyId, int $rejectedBy, string $reason): ?AdvanceSalaryResponseDTO
    {
        return DB::transaction(function () use ($id, $companyId, $rejectedBy, $reason) {
            try {
                Log::info('AdvanceSalaryService::rejectAdvance started', [
                    'advance_id' => $id,
                    'company_id' => $companyId,
                    'rejected_by' => $rejectedBy,
                    'reason_length' => strlen($reason)
                ]);

                $advance = $this->advanceSalaryRepository->findAdvanceInCompany($id, $companyId);

                if (!$advance) {
                    Log::warning('AdvanceSalaryService::rejectAdvance - Advance not found', [
                        'advance_id' => $id,
                        'company_id' => $companyId
                    ]);
                    return null;
                }

                if ($advance->status !== 0) {
                    Log::warning('AdvanceSalaryService::rejectAdvance - Cannot reject non-pending request', [
                        'advance_id' => $id,
                        'current_status' => $advance->status
                    ]);
                    throw new \Exception('لا يمكن رفض طلب تم الموافقة عليه مسبقاً');
                }

                // Check hierarchy permissions for staff users
                $rejectingUser = User::find($rejectedBy);
                if ($rejectingUser && $rejectingUser->user_type !== 'company') {
                    $employee = User::find($advance->employee_id);
                    $hasPermission = false;

                    // Check hierarchy level strict
                    if ($rejectingUser->employee) {
                        $targetHierarchy = $employee->hierarchy_level ?? 999;
                        if ($rejectingUser->hierarchy_level < $targetHierarchy) {
                            $hasPermission = true;
                        }
                    }

                    if (!$hasPermission && (!$employee || !$this->permissionService->canViewEmployeeRequests($rejectingUser, $employee))) {
                        Log::warning('AdvanceSalaryService::rejectAdvance - Hierarchy permission denied', [
                            'advance_id' => $id,
                            'rejector_id' => $rejectedBy,
                            'employee_id' => $advance->employee_id
                        ]);
                        throw new \Exception('ليس لديك صلاحية لرفض طلب هذا الموظف');
                    }
                }

                $rejectedAdvance = $this->advanceSalaryRepository->rejectAdvance($advance, $rejectedBy, $reason);

                // Send rejection notification
                $this->notificationService->sendApprovalNotification(
                    'advance_salary_settings',
                    (string)$rejectedAdvance->advance_salary_id,
                    $companyId,
                    StringStatusEnum::REJECTED->value,
                    $rejectedBy,
                    null,
                    $advance->employee_id
                );

                // Send email notification
                $employeeEmail = $advance->employee->email ?? null;
                $employeeName = $advance->employee->full_name ?? 'Employee';

                if ($employeeEmail) {
                    SendEmailNotificationJob::dispatch(
                        new AdvanceSalaryRejected(
                            employeeName: $employeeName,
                            amount: (float)$advance->advance_amount,
                            salaryType: $advance->salary_type,
                            reason: $reason
                        ),
                        $employeeEmail
                    );
                }

                Log::info('AdvanceSalaryService::rejectAdvance completed successfully', [
                    'advance_id' => $id,
                    'employee_id' => $advance->employee_id,
                    'amount' => $advance->advance_amount,
                    'salary_type' => $advance->salary_type,
                    'rejected_by' => $rejectedBy,
                    'rejection_reason' => $reason
                ]);

                return AdvanceSalaryResponseDTO::fromModel($rejectedAdvance);
            } catch (\Exception $e) {
                Log::error('AdvanceSalaryService::rejectAdvance failed', [
                    'advance_id' => $id,
                    'company_id' => $companyId,
                    'rejected_by' => $rejectedBy,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Get advance salary/loan statistics
     */
    public function getAdvanceStatistics(int $companyId): array
    {
        return $this->advanceSalaryRepository->getAdvanceStatistics($companyId);
    }

    /**
     * Update total paid amount
     */
    public function updateTotalPaid(int $id, int $companyId, float $amount): ?AdvanceSalaryResponseDTO
    {
        $advance = $this->advanceSalaryRepository->findAdvanceInCompany($id, $companyId);

        if (!$advance) {
            return null;
        }

        if ($advance->status !== 1) {
            throw new \Exception('يمكن تحديث المبلغ المدفوع للطلبات الموافق عليها فقط');
        }

        $updatedAdvance = $this->advanceSalaryRepository->updateTotalPaid($advance, $amount);
        return AdvanceSalaryResponseDTO::fromModel($updatedAdvance);
    }

    /**
     * Mark as deducted from salary
     */
    public function markAsDeducted(int $id, int $companyId): ?AdvanceSalaryResponseDTO
    {
        $advance = $this->advanceSalaryRepository->findAdvanceInCompany($id, $companyId);

        if (!$advance) {
            return null;
        }

        if ($advance->status !== 1) {
            throw new \Exception('يمكن تحديد الخصم من الراتب للطلبات الموافق عليها فقط');
        }

        $updatedAdvance = $this->advanceSalaryRepository->markAsDeducted($advance);
        return AdvanceSalaryResponseDTO::fromModel($updatedAdvance);
    }
}
