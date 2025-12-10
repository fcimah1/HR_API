<?php

namespace App\Services;

use App\Repository\Interface\OvertimeRepositoryInterface;
use App\DTOs\Overtime\OvertimeRequestFilterDTO;
use App\DTOs\Overtime\CreateOvertimeRequestDTO;
use App\DTOs\Overtime\UpdateOvertimeRequestDTO;
use App\DTOs\Overtime\OvertimeRequestResponseDTO;
use App\DTOs\Overtime\OvertimeStatsDTO;
use App\Models\User;
use App\Services\SimplePermissionService;
use App\Services\ApprovalService;
use App\Services\OvertimeCalculationService;
use App\Enums\StringStatusEnum;
use App\Enums\OvertimeReasonEnum;
use App\Enums\CompensationTypeEnum;
use App\Jobs\SendEmailNotificationJob;
use App\Mail\Overtime\OvertimeSubmitted;
use App\Mail\Overtime\OvertimeApproved;
use App\Mail\Overtime\OvertimeRejected;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OvertimeService
{
    public function __construct(
        private readonly OvertimeRepositoryInterface $overtimeRepository,
        private readonly SimplePermissionService $permissionService,
        private readonly ApprovalService $approvalService,
        private readonly OvertimeCalculationService $calculationService,
        private readonly NotificationService $notificationService,
        private readonly ApprovalWorkflowService $approvalWorkflow
    ) {}

    /**
     * Get paginated overtime requests with permission filters.
     */
    public function getPaginatedRequests(OvertimeRequestFilterDTO $filters, User $user): array
    {
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

        if ($user->user_type === 'company') {
            // Company users: get all requests
            $modifiedFilters = new OvertimeRequestFilterDTO(
                employeeId: $filters->employeeId,
                status: $filters->status,
                overtimeReason: $filters->overtimeReason,
                fromDate: $filters->fromDate,
                toDate: $filters->toDate,
                month: $filters->month,
                search: $filters->search,
                companyId: $effectiveCompanyId,
                perPage: $filters->perPage,
                page: $filters->page
            );
        } else {
            // Staff users: check hierarchy permissions
            $canViewOthers = false;
            $subordinateIds = [];
            
            try {
                // Get all employees in the company except the user
                $allEmployees = User::where('company_id', $effectiveCompanyId)
                    ->where('user_id', '!=', $user->user_id)
                    ->get();
                
                foreach ($allEmployees as $employee) {
                    if ($this->permissionService->canViewEmployeeRequests($user, $employee)) {
                        $canViewOthers = true;
                        $subordinateIds[] = $employee->user_id;
                    }
                }
                
                // Always include the current user's own requests
                $subordinateIds[] = $user->user_id;
                
            } catch (\Exception $e) {
                $canViewOthers = false;
                $subordinateIds = [$user->user_id];
            }
            
            if ($canViewOthers && !empty($subordinateIds)) {
                // Manager: get requests for employees they can view
                $modifiedFilters = new OvertimeRequestFilterDTO(
                    employeeId: null, // Don't filter by specific employee
                    employeeIds: $subordinateIds, // Add subordinate IDs
                    status: $filters->status,
                    overtimeReason: $filters->overtimeReason,
                    fromDate: $filters->fromDate,
                    toDate: $filters->toDate,
                    month: $filters->month,
                    search: $filters->search,
                    companyId: $effectiveCompanyId,
                    perPage: $filters->perPage,
                    page: $filters->page
                );
            } else {
                // Regular employee: only own requests
                $modifiedFilters = new OvertimeRequestFilterDTO(
                    employeeId: $user->user_id,
                    status: $filters->status,
                    overtimeReason: $filters->overtimeReason,
                    fromDate: $filters->fromDate,
                    toDate: $filters->toDate,
                    month: $filters->month,
                    search: $filters->search,
                    companyId: $effectiveCompanyId,
                    perPage: $filters->perPage,
                    page: $filters->page
                );
            }
        }

        return $this->overtimeRepository->getPaginatedRequests($modifiedFilters, $user);
    }

    /**
     * Get overtime enums
     */
    public function getOvertimeEnums(): array
    {
        return [
            'statuses' => StringStatusEnum::toArray(),
            'reasons' => OvertimeReasonEnum::toArray(),
            'compensation_types' => CompensationTypeEnum::toArray(),
        ];
    }

    /**
     * Create overtime request with validation and calculations.
     */
    public function createRequest(CreateOvertimeRequestDTO $dto, User $user): OvertimeRequestResponseDTO
    {
        return DB::transaction(function () use ($dto, $user) {
            Log::info('OvertimeService::createRequest started', [
                'user_id' => $user->user_id,
                'staff_id' => $dto->staffId
            ]);

            // Check hierarchical permissions for staff users creating requests for others
            if ($user->user_type !== 'company' && $dto->staffId !== $user->user_id) {
                $employee = User::find($dto->staffId);
                if (!$employee || !$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    throw new \Exception('ليس لديك صلاحية لإنشاء طلب عمل إضافي لهذا الموظف');
                }
            }

            // Validate against shift
            $this->calculationService->validateAgainstShift(
                $dto->staffId,
                $dto->requestDate,
                $dto->clockIn,
                $dto->clockOut,
                $dto->overtimeReason
            );

            // Check if it's a holiday (allow for weekend work reason)
            if ($dto->overtimeReason != 4) {
                $isHoliday = $this->calculationService->isHoliday($dto->companyId, $dto->requestDate);
                if ($isHoliday) {
                    throw new \Exception('لا يمكن تقديم طلب عمل إضافي في يوم عطلة');
                }
            }

            // Calculate total hours
            $totalHours = $this->calculationService->calculateTotalHours($dto->clockIn, $dto->clockOut);

            // Calculate overtime types
            $overtimeTypes = $this->calculationService->calculateOvertimeTypes(
                $totalHours,
                $dto->additionalWorkHours
            );

            // Calculate compensation banked
            $compensationBanked = $this->calculationService->calculateCompensationBanked(
                $totalHours,
                $dto->compensationType
            );

            // Prepare data for creation
            $data = [
                'company_id' => $dto->companyId,
                'staff_id' => $dto->staffId,
                'request_date' => $dto->requestDate,
                'request_month' => $dto->requestMonth,
                'clock_in' => $dto->clockIn,
                'clock_out' => $dto->clockOut,
                'overtime_reason' => $dto->overtimeReason,
                'additional_work_hours' => $dto->additionalWorkHours,
                'compensation_type' => $dto->compensationType,
                'request_reason' => $dto->requestReason,
                'straight' => $overtimeTypes['straight'],
                'time_a_half' => $overtimeTypes['time_a_half'],
                'double_overtime' => $overtimeTypes['double_overtime'],
                'total_hours' => $totalHours,
                'compensation_banked' => $compensationBanked,
                'is_approved' => 0, // Always pending
                'created_at' => date('d-m-Y H:i:s'),
            ];

            $request = $this->overtimeRepository->createRequest($data);

               // Start approval workflow if multi-level approval is enabled
            $this->approvalWorkflow->submitForApproval(
                'overtime_request_settings',
                (string)$request->time_request_id,
                $dto->companyId,
                $dto->staffId
            );
            // Send submission notification
            $notificationsSent = $this->notificationService->sendSubmissionNotification(
                'overtime_request_settings',
                (string)$request->time_request_id,
                $dto->companyId,
                StringStatusEnum::SUBMITTED->value,
                $dto->staffId
            );

            // If no notifications were sent (due to missing/invalid configuration),
            // send a notification to the employee as fallback
            if ($notificationsSent === 0 || $notificationsSent === null || !isset($notificationsSent)) {
                $this->notificationService->sendCustomNotification(
                    'overtime_request_settings',
                    (string)$request->time_request_id,
                    [$dto->staffId],
                    StringStatusEnum::SUBMITTED->value
                );
            }
            // Send email notification
            $employeeEmail = $request->employee->email ?? null;
            $employeeName = $request->employee->full_name ?? 'Employee';

            if ($employeeEmail) {
                SendEmailNotificationJob::dispatch(
                    new OvertimeSubmitted(
                        employeeName: $employeeName,
                        requestDate: $dto->requestDate,
                        totalHours: $totalHours,
                        reason: $dto->requestReason
                    ),
                    $employeeEmail
                );
            }

            Log::info('OvertimeService::createRequest completed', [
                'request_id' => $request->time_request_id
            ]);

            return OvertimeRequestResponseDTO::fromModel($request);
        });
    }

    /**
     * Update overtime request (only if pending and owner).
     */
    public function updateRequest(int $id, UpdateOvertimeRequestDTO $dto, User $user): OvertimeRequestResponseDTO
    {
        return DB::transaction(function () use ($id, $dto, $user) {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $request = $this->overtimeRepository->findRequestInCompany($id, $effectiveCompanyId);

            if (!$request) {
                throw new \Exception('الطلب غير موجود');
            }

            // Check hierarchical permissions (owner, company, or authorized managers can update)
            $isOwner = $request->staff_id === $user->user_id;
            $isCompany = $user->user_type === 'company';
            
            if (!$isOwner && !$isCompany) {
                $employee = User::find($request->staff_id);
                if (!$employee || !$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    throw new \Exception('ليس لديك صلاحية لتعديل هذا الطلب');
                }
            }

            // Can only update pending requests
            if ($request->is_approved !== 0) {
                throw new \Exception('لا يمكن تعديل طلب تمت مراجعته');
            }

            // Validate against shift
            $this->calculationService->validateAgainstShift(
                $request->staff_id,
                $dto->requestDate,
                $this->calculationService->convertTo24Hour($dto->clockIn, $dto->requestDate),
                $this->calculationService->convertTo24Hour($dto->clockOut, $dto->requestDate),
                $dto->overtimeReason
            );

            // Convert times
            $clockIn24 = $this->calculationService->convertTo24Hour($dto->clockIn, $dto->requestDate);
            $clockOut24 = $this->calculationService->convertTo24Hour($dto->clockOut, $dto->requestDate);

            // Calculate total hours
            $totalHours = $this->calculationService->calculateTotalHours($clockIn24, $clockOut24);

            // Calculate overtime types
            $overtimeTypes = $this->calculationService->calculateOvertimeTypes(
                $totalHours,
                $dto->additionalWorkHours
            );

            // Calculate compensation banked
            $compensationBanked = $this->calculationService->calculateCompensationBanked(
                $totalHours,
                $dto->compensationType
            );

            // Prepare update data
            $updateData = [
                'request_date' => $dto->requestDate,
                'request_month' => $this->calculationService->calculateRequestMonth($dto->requestDate),
                'clock_in' => $clockIn24,
                'clock_out' => $clockOut24,
                'overtime_reason' => $dto->overtimeReason,
                'additional_work_hours' => $dto->additionalWorkHours,
                'compensation_type' => $dto->compensationType,
                'request_reason' => $dto->requestReason,
                'straight' => $overtimeTypes['straight'],
                'time_a_half' => $overtimeTypes['time_a_half'],
                'double_overtime' => $overtimeTypes['double_overtime'],
                'total_hours' => $totalHours,
                'compensation_banked' => $compensationBanked,
            ];

            $updatedRequest = $this->overtimeRepository->updateRequest($request, $updateData);

            return OvertimeRequestResponseDTO::fromModel($updatedRequest);
        });
    }

    /**
     * Delete overtime request (only if pending and owner).
     */
    public function deleteRequest(int $id, User $user): bool
    {
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

        $request = $this->overtimeRepository->findRequestInCompany($id, $effectiveCompanyId);

        if (!$request) {
            throw new \Exception('الطلب غير موجود');
        }

        // Check hierarchical permissions (owner, company, or authorized managers can delete)
        $isOwner = $request->staff_id === $user->user_id;
        $isCompany = $user->user_type === 'company';
        
        if (!$isOwner && !$isCompany) {
            $employee = User::find($request->staff_id);
            if (!$employee || !$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                throw new \Exception('ليس لديك صلاحية لحذف هذا الطلب');
            }
        }

        // Can only delete pending requests
        if ($request->is_approved !== 0) {
            throw new \Exception('لا يمكن حذف طلب تمت مراجعته');
        }

        return $this->overtimeRepository->deleteRequest($request);
    }

    /**
     * Approve overtime request with multi-level approval workflow.
     */
    public function approveRequest(int $id, User $approver, ?string $remarks = null): OvertimeRequestResponseDTO
    {
        return DB::transaction(function () use ($id, $approver, $remarks) {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($approver);

            $request = $this->overtimeRepository->findRequestInCompany($id, $effectiveCompanyId);

            if (!$request) {
                throw new \Exception('الطلب غير موجود');
            }

            if ($request->is_approved !== 0) {
                throw new \Exception('تمت مراجعة هذا الطلب مسبقاً');
            }

            $userType = strtolower(trim($approver->user_type ?? ''));

            // Company user can approve directly
            if ($userType === 'company') {
                $approvedRequest = $this->overtimeRepository->approveRequest($request);

                // Send approval notification
                $this->notificationService->sendApprovalNotification(
                    'overtime_request_settings',
                    (string)$request->time_request_id,
                    $effectiveCompanyId,
                    StringStatusEnum::APPROVED->value,
                    $approver->user_id,
                    1,
                    $request->staff_id
                );

                // Send email notification
                $employeeEmail = $request->employee->email ?? null;
                $employeeName = $request->employee->full_name ?? 'Employee';

                if ($employeeEmail) {
                    SendEmailNotificationJob::dispatch(
                        new OvertimeApproved(
                            employeeName: $employeeName,
                            requestDate: $request->request_date,
                            totalHours: $request->total_hours,
                            remarks: $remarks
                        ),
                        $employeeEmail
                    );
                }

                // Record final approval
                $this->approvalService->recordApproval(
                    $request->time_request_id,
                    $approver->user_id,
                    1, // approved
                    1, // final level
                    'overtime_request_settings',
                    $effectiveCompanyId
                );

                return OvertimeRequestResponseDTO::fromModel($approvedRequest);
            }

            // For staff users, use multi-level approval
            $canApprove = $this->approvalService->canUserApprove(
                $approver->user_id,
                $request->time_request_id,
                $request->staff_id,
                'overtime_request_settings'
            );

            if (!$canApprove) {
                throw new \Exception('ليس لديك صلاحية للموافقة على هذا الطلب في المرحلة الحالية');
            }

            $isFinal = $this->approvalService->isFinalApproval(
                $request->time_request_id,
                $request->staff_id,
                'overtime_request_settings'
            );

            if ($isFinal) {
                // Final approval - update request status
                $approvedRequest = $this->overtimeRepository->approveRequest($request);

                // Send approval notification
                $this->notificationService->sendApprovalNotification(
                    'overtime_request_settings',
                    (string)$request->time_request_id,
                    $effectiveCompanyId,
                    StringStatusEnum::APPROVED->value,
                    $approver->user_id,
                    null,
                    $request->staff_id
                );

                // Record final approval
                $this->approvalService->recordApproval(
                    $request->time_request_id,
                    $approver->user_id,
                    1, // approved
                    1, // final level
                    'overtime_request_settings',
                    $effectiveCompanyId
                );

                return OvertimeRequestResponseDTO::fromModel($approvedRequest);
            } else {
                // Intermediate approval - just record it
                $this->approvalService->recordApproval(
                    $request->time_request_id,
                    $approver->user_id,
                    1, // approved
                    0, // intermediate level
                    'overtime_request_settings',
                    $effectiveCompanyId
                );

                // Reload to get updated approvals
                $request->refresh();
                $request->load(['employee', 'approvals.staff']);

                return OvertimeRequestResponseDTO::fromModel($request);
            }
        });
    }

    /**
     * Reject overtime request.
     */
    public function rejectRequest(int $id, User $rejector, string $reason): OvertimeRequestResponseDTO
    {
        return DB::transaction(function () use ($id, $rejector, $reason) {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($rejector);

            $request = $this->overtimeRepository->findRequestInCompany($id, $effectiveCompanyId);

            if (!$request) {
                throw new \Exception('الطلب غير موجود');
            }

            if ($request->is_approved !== 0) {
                throw new \Exception('تمت مراجعة هذا الطلب مسبقاً');
            }

            // Reject the request
            $rejectedRequest = $this->overtimeRepository->rejectRequest($request, $reason);

            // Send rejection notification
            $this->notificationService->sendApprovalNotification(
                'overtime_request_settings',
                (string)$request->time_request_id,
                $effectiveCompanyId,
                StringStatusEnum::REJECTED->value,
                $rejector->user_id,
                null,
                $request->staff_id
            );

            // Send email notification
            $employeeEmail = $request->employee->email ?? null;
            $employeeName = $request->employee->full_name ?? 'Employee';

            if ($employeeEmail) {
                SendEmailNotificationJob::dispatch(
                    new OvertimeRejected(
                        employeeName: $employeeName,
                        requestDate: $request->request_date,
                        totalHours: $request->total_hours,
                        reason: $reason
                    ),
                    $employeeEmail
                );
            }

            // Record rejection
            $this->approvalService->recordApproval(
                $request->time_request_id,
                $rejector->user_id,
                2, // rejected
                2, // rejection level
                'overtime_request_settings',
                $effectiveCompanyId
            );

            return OvertimeRequestResponseDTO::fromModel($rejectedRequest);
        });
    }

    /**
     * Get requests requiring approval from specific user.
     */
    public function getRequestsForApproval(User $user): array
    {
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

        $requests = $this->overtimeRepository->getRequestsRequiringApproval(
            $user->user_id,
            $effectiveCompanyId
        );

        return array_map(
            fn($request) => OvertimeRequestResponseDTO::fromModel($request)->toArray(),
            $requests
        );
    }

    /**
     * Get team requests (for managers).
     */
    public function getTeamRequests(User $manager): array
    {
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($manager);

        $requests = $this->overtimeRepository->getRequestsByManager(
            $manager->user_id,
            $effectiveCompanyId
        );

        return array_map(
            fn($request) => OvertimeRequestResponseDTO::fromModel($request)->toArray(),
            $requests
        );
    }

    /**
     * Get overtime statistics.
     */
    public function getStats(User $user, ?string $fromDate = null, ?string $toDate = null): OvertimeStatsDTO
    {
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

        $stats = $this->overtimeRepository->getStats($effectiveCompanyId, $fromDate, $toDate);

        return OvertimeStatsDTO::fromData($stats);
    }

    /**
     * Get single overtime request.
     */
    public function getRequest(int $id, User $user): OvertimeRequestResponseDTO
    {
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

        $request = $this->overtimeRepository->findRequestInCompany($id, $effectiveCompanyId);

        if (!$request) {
            throw new \Exception('الطلب غير موجود');
        }

        // Check hierarchical permissions
        if ($user->user_type !== 'company' && $request->staff_id !== $user->user_id) {
            $employee = User::find($request->staff_id);
            if (!$employee || !$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                throw new \Exception('ليس لديك صلاحية لعرض هذا الطلب');
            }
        }

        return OvertimeRequestResponseDTO::fromModel($request);
    }
}
