<?php

namespace App\Services;

use App\DTOs\Travel\CreateTravelDTO;
use App\DTOs\Travel\TravelRequestFilterDTO;
use App\DTOs\Travel\UpdateTravelDTO;
use App\Http\Requests\Travel\UpdateTravelStatusRequest;
use App\Mail\Travel\TravelSubmitted;
use App\Mail\Travel\TravelUpdated;
use App\Mail\Travel\TravelApproved;
use App\Mail\Travel\TravelRejected;
use App\Models\User;
use App\Repository\Interface\TravelRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\SimplePermissionService;
use App\Jobs\SendEmailNotificationJob;
use App\Enums\StringStatusEnum;
use App\Enums\TravelModeEnum;
use App\Enums\TravelStatusEnum;
use App\Models\Travel;
use Illuminate\Support\Facades\Auth;

class TravelService
{
    public function __construct(
        protected TravelRepositoryInterface $travelRepository,
        protected SimplePermissionService $permissionService,
        protected NotificationService $notificationService,
        protected ApprovalWorkflowService $approvalWorkflow,
        protected Travel $travel,
        protected CacheService $cacheService,
    ) {}

    public function getTravelEnums(): array
    {
        $user = Auth::user();
        if ($user) {
            $companyId = $user->company_id;
            if ($companyId === 0) {
                $companyId = $this->permissionService->getEffectiveCompanyId($user);
            }


            $arrangementTypes = $this->travel->allArrangementTypeName($companyId);
            Log::info('TravelService::getTravelEnums - arrangementTypes', [
                'companyId' => $companyId,
                'arrangementTypes' => $arrangementTypes,
            ]);
            $restrictedTypes = $this->permissionService->getRestrictedValues($user->user_id, $companyId, 'travel_type_');
            Log::info('TravelService::getTravelEnums - restrictedTypes', [
                'restrictedTypes' => $restrictedTypes,
            ]);
            if (!empty($restrictedTypes)) {
                $arrangementTypes = array_filter($arrangementTypes, function ($key) use ($restrictedTypes) {
                    return !in_array($key, $restrictedTypes);
                }, ARRAY_FILTER_USE_KEY);
            }
        }

        return [
            'statuses' => TravelStatusEnum::toArray(),
            'travel_modes' => TravelModeEnum::toArray(),
            'arrangement_types_names' => $arrangementTypes,
        ];
    }

    public function createTravel(CreateTravelDTO $dto, User $user): object
    {
        return DB::transaction(function () use ($dto, $user) {

            // Check hierarchy permissions for staff users creating for other employees
            if ($user->user_type !== 'company' && $dto->employee_id !== $user->user_id) {
                $employee = User::find($dto->employee_id);
                if (!$employee || !$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    Log::warning('TravelService::createTravel - Hierarchy permission denied', [
                        'requester_id' => $user->user_id,
                        'requester_type' => $user->user_type,
                        'target_employee_id' => $dto->employee_id,
                        'requester_level' => $this->permissionService->getUserHierarchyLevel($user),
                        'target_level' => $this->permissionService->getUserHierarchyLevel($employee),
                        'requester_department' => $this->permissionService->getUserDepartmentId($user),
                        'target_department' => $this->permissionService->getUserDepartmentId($employee),
                        'message' => 'ليس لديك صلاحية لإنشاء طلب سفر لهذا الموظف',
                    ]);
                    throw new \Exception('ليس لديك صلاحية لإنشاء طلب سفر لهذا الموظف');
                }
            }

            $currentEmployee = User::find($dto->employee_id);
            if ($currentEmployee) {
                // Check for restricted travel types
                // Check for restricted travel types
                $restrictedTravelTypes = $this->permissionService->getRestrictedValues($dto->employee_id, $dto->company_id, 'travel_type_');

                // Check if restriction override is possible (Company Owner or Superior)
                // Use the user who is physically creating the request ($user) vs the target employee ($currentEmployee)
                $canOverride = $this->permissionService->canOverrideRestriction(
                    $user,
                    $currentEmployee,
                    'travel_type_',
                    $dto->arrangement_type
                );

                if (!$canOverride && in_array($dto->arrangement_type, $restrictedTravelTypes)) {
                    Log::warning('TravelService::createTravel - Restricted travel type', [
                        'employee_id' => $dto->employee_id,
                        'arrangement_type' => $dto->arrangement_type,
                        'restricted_types' => $restrictedTravelTypes,
                        'user_id' => $user->user_id,
                        'message' => 'هذا النوع من السفر مقيد لهذا الموظف',
                    ]);
                    throw new \Exception('هذا النوع من السفر مقيد لهذا الموظف');
                }
            }

            if ($this->travelRepository->hasOverlappingTravel($dto->employee_id, $dto->start_date, $dto->end_date)) {
                Log::warning('TravelService::createTravel - Overlapping travel found', [
                    'employee_id' => $dto->employee_id,
                    'start_date' => $dto->start_date,
                    'end_date' => $dto->end_date,
                    'message' => 'يوجد طلب سفر آخر لنفس الموظف في نفس الفترة الزمنية أو فترة متداخلة معها',
                ]);
                throw new \Exception('يوجد طلب سفر آخر لنفس الموظف في نفس الفترة الزمنية أو فترة متداخلة معها');
            }

            $travel = $this->travelRepository->create($dto);

            // Send submission notifications
            $notificationsSent = $this->notificationService->sendSubmissionNotification(
                'travel_settings',
                (string)$travel->travel_id,
                $dto->company_id,
                StringStatusEnum::SUBMITTED->value,
                $dto->employee_id
            );

            // Start approval workflow
            $this->approvalWorkflow->submitForApproval(
                'travel_settings',
                (string)$travel->travel_id,
                $dto->employee_id,
                $dto->company_id
            );

            // If no notifications were sent (due to missing/invalid configuration),
            // send a notification to the employee as fallback
            if ($notificationsSent === 0 || $notificationsSent === null || !isset($notificationsSent)) {
                $this->notificationService->sendCustomNotification(
                    'travel_settings',
                    (string)$travel->travel_id,
                    [$dto->employee_id],
                    StringStatusEnum::SUBMITTED->value
                );
            }

            // Send email notification
            $employeeEmail = $travel->employee->email ?? null;
            $employeeName = $travel->employee->full_name ?? 'Employee';

            if ($employeeEmail) {
                SendEmailNotificationJob::dispatch(
                    new TravelSubmitted(
                        employeeName: $employeeName,
                        destination: $travel->visit_place,
                        startDate: $travel->start_date,
                        endDate: $travel->end_date,
                        purpose: $travel->visit_purpose
                    ),
                    $employeeEmail
                );
            }

            return $travel;
        });
    }

    public function updateTravel(int $id, UpdateTravelDTO $dto, User $user): object
    {
        return DB::transaction(function () use ($id, $dto, $user) {

            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $travel = $this->travelRepository->findByIdAndCompany($id, $effectiveCompanyId);

            if (!$travel) {
                Log::warning('TravelService::updateTravel - Travel not found', [
                    'travel_id' => $id,
                    'message' => 'الطلب غير موجود',
                    'user_id' => $user->user_id,
                ]);
                throw new \Exception('الطلب غير موجود');
            }

            // Check permissions (only owner, company, or managers can update, and usually only if pending)
            $isOwner = $travel->employee_id === $user->user_id;
            $isCompany = $user->user_type === 'company';

            // If not owner or company, check hierarchy permissions
            if (!$isOwner && !$isCompany) {
                $employee = User::find($travel->employee_id);
                if (!$employee || !$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    Log::warning('TravelService::updateTravel - Hierarchy permission denied', [
                        'travel_id' => $id,
                        'requester_id' => $user->user_id,
                        'requester_type' => $user->user_type,
                        'travel_employee_id' => $travel->employee_id,
                        'requester_level' => $this->permissionService->getUserHierarchyLevel($user),
                        'employee_level' => $this->permissionService->getUserHierarchyLevel($employee),
                        'requester_department' => $this->permissionService->getUserDepartmentId($user),
                        'employee_department' => $this->permissionService->getUserDepartmentId($employee),
                        'message' => 'ليس لديك صلاحية لتحديث طلب سفر هذا الموظف',
                    ]);
                    throw new \Exception('ليس لديك صلاحية لتحديث طلب سفر هذا الموظف');
                }
            }

            if ($travel->status == 1) {
                Log::warning('TravelService::updateTravel - Travel already approved', [
                    'travel_id' => $id,
                    'message' => 'لا يمكن تحديث طلب سفر بعد الموافقة عليه',
                    'user_id' => $user->user_id,
                ]);
                throw new \Exception(' لا يمكن تحديث طلب سفر بعد الموافقة عليه');
            }

            if ($travel->status == 2) {
                Log::warning('TravelService::updateTravel - Travel already rejected', [
                    'travel_id' => $id,
                    'message' => 'لا يمكن تحديث طلب سفر بعد رفضه',
                    'user_id' => $user->user_id,
                ]);
                throw new \Exception(' لا يمكن تحديث طلب سفر بعد رفضه');
            }

            // Enforce restrictions on update
            $targetEmployee = User::find($travel->employee_id);
            $typeToCheck = $dto->arrangement_type ?? $travel->arrangement_type;

            // Check if requester can override restrictions
            $canOverride = false;
            // Check override: Requester != Target AND canOverrideRestriction
            if ($user->user_id !== $targetEmployee->user_id) {
                if ($this->permissionService->canOverrideRestriction($user, $targetEmployee, 'travel_type_', (int)$typeToCheck)) {
                    $canOverride = true;
                }
            }

            if (!$canOverride) {
                $restrictedTypes = $this->permissionService->getRestrictedValues($targetEmployee->user_id, $effectiveCompanyId, 'travel_type_');
                if (in_array($typeToCheck, $restrictedTypes)) {
                    Log::warning('TravelService::updateTravel - Restricted travel type update attempt', [
                        'travel_id' => $id,
                        'arrangement_type' => $typeToCheck,
                        'user_id' => $user->user_id,
                        'message' => 'نوع السفر في هذا الطلب مقيد، لا يمكن تعديل الطلب.'
                    ]);
                    throw new \Exception('نوع السفر في هذا الطلب مقيد، لا يمكن تعديل الطلب.');
                }
            }

            // Check for overlapping travel dates (if dates are being updated)
            $startDate = $dto->start_date ?? $travel->start_date;
            $endDate = $dto->end_date ?? $travel->end_date;

            if ($this->travelRepository->hasOverlappingTravel($travel->employee_id, $startDate, $endDate, $id)) {
                Log::warning('TravelService::updateTravel - Overlapping travel found', [
                    'travel_id' => $id,
                    'message' => 'يوجد طلب سفر آخر لنفس الموظف في نفس الفترة الزمنية أو فترة متداخلة معها',
                    'user_id' => $user->user_id,
                ]);
                throw new \Exception('يوجد طلب سفر آخر لنفس الموظف في نفس الفترة الزمنية أو فترة متداخلة معها');
            }

            $this->travelRepository->update($travel, $dto);


            // Send email notification
            $employeeEmail = $travel->employee->email ?? null;
            $employeeName = $travel->employee->full_name ?? 'Employee';

            if ($employeeEmail) {
                SendEmailNotificationJob::dispatch(
                    new TravelUpdated(
                        employeeName: $employeeName,
                        destination: $travel->visit_place,
                        startDate: $travel->start_date,
                        endDate: $travel->end_date,
                        purpose: $travel->visit_purpose
                    ),
                    $employeeEmail
                );
            }

            return $travel;
        });
    }

    public function cancelTravel(int $id, User $user): bool
    {
        return DB::transaction(function () use ($id, $user) {

            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $travel = $this->travelRepository->findByIdAndCompany($id, $effectiveCompanyId);

            if (!$travel) {
                Log::warning('TravelService::cancelTravel - Travel not found', [
                    'travel_id' => $id,
                    'message' => 'الطلب غير موجود',
                    'user_id' => $user->user_id,
                ]);
                throw new \Exception('الطلب غير موجود');
            }

            // 1. Status Check: Only Pending (0) can be cancelled
            if ($travel->status !== 0) {
                Log::warning('TravelService::cancelTravel - Travel not pending', [
                    'travel_id' => $id,
                    'message' => 'لا يمكن إلغاء الطلب بعد المراجعة (موافق عليه أو مرفوض)',
                    'user_id' => $user->user_id,
                ]);
                throw new \Exception('لا يمكن إلغاء الطلب بعد المراجعة (موافق عليه أو مرفوض)');
            }

            // 2. Permission Check

            // Enforce restrictions on cancel
            $targetEmployee = User::find($travel->employee_id);
            $typeToCheck = $travel->arrangement_type;

            // Check if requester can override restrictions
            $canOverride = false;
            if ($user->user_id !== $targetEmployee->user_id) {
                if ($this->permissionService->canOverrideRestriction($user, $targetEmployee, 'travel_type_', (int)$typeToCheck)) {
                    $canOverride = true;
                }
            }

            if (!$canOverride && $user->user_type !== 'company') {
                $restrictedTypes = $this->permissionService->getRestrictedValues($targetEmployee->user_id, $effectiveCompanyId, 'travel_type_');
                if (in_array($typeToCheck, $restrictedTypes)) {
                    Log::warning('TravelService::cancelTravel - Restricted travel type cancel attempt', [
                        'travel_id' => $id,
                        'arrangement_type' => $typeToCheck,
                        'user_id' => $user->user_id,
                        'message' => 'نوع السفر في هذا الطلب مقيد، لا يمكن إلغاء الطلب.'
                    ]);
                    throw new \Exception('نوع السفر في هذا الطلب مقيد، لا يمكن إلغاء الطلب.');
                }
            }

            $isOwner = $travel->employee_id === $user->user_id;
            $isCompany = $user->user_type === 'company';

            // Check hierarchy permission (is a manager of the employee)
            $isHierarchyManager = false;
            if (!$isOwner && !$isCompany) {
                $employee = User::find($travel->employee_id);
                if ($employee && $this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    $isHierarchyManager = true;
                }
            }

            if (!$isOwner && !$isCompany && !$isHierarchyManager) {
                Log::warning('TravelService::cancelTravel - Permission denied', [
                    'travel_id' => $id,
                    'message' => 'ليس لديك صلاحية لإلغاء هذا الطلب',
                    'user_id' => $user->user_id,
                ]);
                throw new \Exception('ليس لديك صلاحية لإلغاء هذا الطلب');
            }


            $this->travelRepository->reject($id, $user->user_id);

            // Send notification for cancellation
            $this->notificationService->sendSubmissionNotification(
                'travel_settings',
                (string)$id,
                $effectiveCompanyId,
                StringStatusEnum::REJECTED->value
            );

            // Send email notification
            $employeeEmail = $travel->employee->email ?? null;
            $employeeName = $travel->employee->full_name ?? 'Employee';

            if ($employeeEmail) {
                SendEmailNotificationJob::dispatch(
                    new TravelRejected(
                        employeeName: $employeeName,
                        destination: $travel->visit_place,
                        startDate: $travel->start_date,
                        endDate: $travel->end_date,
                        purpose: $travel->visit_purpose
                    ),
                    $employeeEmail
                );
            }
            return true;
        });
    }

    public function approveTravel(int $id, UpdateTravelStatusRequest $request, User $user): object
    {
        return DB::transaction(function () use ($id, $request, $user) {

            // Get the authenticated user from the request
            $user = $request->user();

            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $travel = $this->travelRepository->findByIdAndCompany($id, $effectiveCompanyId);

            if (!$travel) {
                Log::warning('TravelService::approveTravel - Travel not found', [
                    'travel_id' => $id,
                    'message' => 'الطلب غير موجود',
                    'user_id' => $user->user_id,
                ]);
                throw new \Exception('الطلب غير موجود');
            }

            if ($travel->status !== 0) {
                Log::warning('TravelService::approveTravel - Travel not pending', [
                    'travel_id' => $id,
                    'message' => 'تم الموافقة على هذا الطلب مسبقاً أو تم رفضه',
                    'user_id' => $user->user_id,
                ]);
                throw new \Exception('تم الموافقة على هذا الطلب مسبقاً أو تم رفضه');
            }

            // Check hierarchy permissions for staff users
            if ($user->user_type !== 'company') {
                $employee = User::find($travel->employee_id);
                if (!$employee || !$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    Log::warning('TravelService::approveTravel - Hierarchy permission denied', [
                        'travel_id' => $id,
                        'requester_id' => $user->user_id,
                        'requester_type' => $user->user_type,
                        'employee_id' => $travel->employee_id,
                        'requester_level' => $this->permissionService->getUserHierarchyLevel($user),
                        'employee_level' => $this->permissionService->getUserHierarchyLevel($employee),
                        'requester_department' => $this->permissionService->getUserDepartmentId($user),
                        'employee_department' => $this->permissionService->getUserDepartmentId($employee),
                        'message' => 'ليس لديك صلاحية للموافقة على طلب هذا الموظف',
                    ]);
                    throw new \Exception('ليس لديك صلاحية للموافقة على طلب هذا الموظف');
                }
            }

            $travel = $this->travelRepository->approve($id, $user->user_id);

            // Get employee's hierarchy level for policy lookup
            $employee = User::find($travel->employee_id);
            $employeeHierarchyLevel = $this->permissionService->getUserHierarchyLevel($employee);

            // Get travel allowance from PolicyResult based on policy_id=1 (Travel) and hierarchy_level
            $allowanceAmount = null;
            $currency = null;
            $policyResult = \App\Models\PolicyResult::where('policy_id', 1) // 1 = Travel
                ->where('hierarchy_level', $employeeHierarchyLevel)
                ->where('company_id', $effectiveCompanyId)
                ->first();

            if ($policyResult) {
                $allowanceAmount = $policyResult->total_amount;
                $currency = $policyResult->currency_local;
            }


            $this->notificationService->sendApprovalNotification(
                'travel_settings',
                (string)$travel->travel_id,
                $effectiveCompanyId,
                StringStatusEnum::APPROVED->value,
                $user->user_id,  // Approver ID
                $travel->employee_id // Submitter ID
            );

            // Send email notification
            $employeeEmail = $travel->employee->email ?? null;
            $employeeName = $travel->employee->full_name ?? 'Employee';

            if ($employeeEmail) {
                SendEmailNotificationJob::dispatch(
                    new TravelApproved(
                        employeeName: $employeeName,
                        destination: $travel->visit_place,
                        startDate: $travel->start_date,
                        endDate: $travel->end_date,
                        remarks: null,
                        allowanceAmount: $allowanceAmount,
                        currency: $currency
                    ),
                    $employeeEmail
                );
            }

            return $travel;
        });
    }

    public function rejectTravel(int $id, UpdateTravelStatusRequest $request, User $user): object
    {
        return DB::transaction(function () use ($id, $request, $user) {

            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $travel = $this->travelRepository->findByIdAndCompany($id, $effectiveCompanyId);

            if (!$travel) {
                Log::warning('TravelService::rejectTravel - Travel not found', [
                    'travel_id' => $id,
                    'message' => 'الطلب غير موجود',
                    'user_id' => $user->user_id,
                ]);
                throw new \Exception('الطلب غير موجود');
            }

            if ($travel->status !== 0) {
                Log::warning('TravelService::rejectTravel - Travel not pending', [
                    'travel_id' => $id,
                    'message' => 'تم رفض هذا الطلب مسبقاً أو تم الموافقة عليه',
                    'user_id' => $user->user_id,
                ]);
                throw new \Exception('تم رفض هذا الطلب مسبقاً أو تم الموافقة عليه');
            }

            // Check hierarchy permissions for staff users
            if ($user->user_type !== 'company') {
                $employee = User::find($travel->employee_id);
                if (!$employee || !$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                    Log::warning('TravelService::rejectTravel - Hierarchy permission denied', [
                        'travel_id' => $id,
                        'requester_id' => $user->user_id,
                        'requester_type' => $user->user_type,
                        'employee_id' => $travel->employee_id,
                        'requester_level' => $this->permissionService->getUserHierarchyLevel($user),
                        'employee_level' => $this->permissionService->getUserHierarchyLevel($employee),
                        'requester_department' => $this->permissionService->getUserDepartmentId($user),
                        'employee_department' => $this->permissionService->getUserDepartmentId($employee),
                        'message' => 'ليس لديك صلاحية لرفض طلب هذا الموظف',
                    ]);
                    throw new \Exception('ليس لديك صلاحية لرفض طلب هذا الموظف');
                }
            }

            $travel = $this->travelRepository->reject($id, $user->user_id);

            // Send rejection notification
            $this->notificationService->sendApprovalNotification(
                'travel_settings',
                (string)$travel->travel_id,
                $effectiveCompanyId,
                StringStatusEnum::REJECTED->value,
                $user->user_id,  // Rejector ID
                null,
                $travel->employee_id // Submitter ID
            );

            // Send email notification
            $employeeEmail = $travel->employee->email ?? null;
            $employeeName = $travel->employee->full_name ?? 'Employee';

            if ($employeeEmail) {
                SendEmailNotificationJob::dispatch(
                    new TravelRejected(
                        employeeName: $employeeName,
                        destination: $travel->visit_place,
                        startDate: $travel->start_date,
                        endDate: $travel->end_date,
                        purpose: 'تم رفض طلب السفر'
                    ),
                    $employeeEmail
                );
            }

            return $travel;
        });
    }

    public function getTravels(User $user, TravelRequestFilterDTO $filters)
    {
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

        if ($user->user_type === 'company') {
            return $this->travelRepository->getByCompany($effectiveCompanyId, $filters);
        } else {
            // Staff users: check hierarchy permissions
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // Check if user can view other employees' requests (has subordinates)
            $canViewOthers = false;
            $subordinateIds = [];

            try {
                // Get all employees in the company except the user
                $allEmployees = User::where('company_id', $effectiveCompanyId)
                    ->where('user_id', '!=', $user->user_id)
                    ->get();

                foreach ($allEmployees as $employee) {
                    Log::info('getTravels - Checking employee permission', [
                        'user_id' => $user->user_id,
                        'employee_id' => $employee->user_id,
                        'employee_name' => $employee->full_name,
                        'can_view' => $this->permissionService->canViewEmployeeRequests($user, $employee)
                    ]);

                    if ($this->permissionService->canViewEmployeeRequests($user, $employee)) {
                        $canViewOthers = true;
                        $subordinateIds[] = $employee->user_id;
                    }
                }

                // Always include the current user's own requests
                $subordinateIds[] = $user->user_id;

                Log::info('getTravels - Permission check results', [
                    'user_id' => $user->user_id,
                    'can_view_others' => $canViewOthers,
                    'subordinate_ids' => $subordinateIds
                ]);
            } catch (\Exception $e) {
                $canViewOthers = false;
                $subordinateIds = [];
            }

            if ($canViewOthers && !empty($subordinateIds)) {
                // Filter restricted types for the viewing user (manager)
                $restrictedTypes = $this->permissionService->getRestrictedValues($user->user_id, $effectiveCompanyId, 'travel_type_');

                // Manager: get requests for employees they can view (subordinates only)
                $newFilters = new TravelRequestFilterDTO(
                    employeeId: null, // Don't filter by specific employee
                    employeeIds: $subordinateIds, // Only subordinate employees
                    status: $filters->status,
                    travelMode: $filters->travelMode,
                    arrangementType: $filters->arrangementType,
                    startDate: $filters->startDate,
                    endDate: $filters->endDate,
                    month: $filters->month,
                    companyId: $effectiveCompanyId,
                    hierarchyLevels: null, // Not needed since we filter by specific IDs
                    search: $filters->search,
                    perPage: $filters->perPage,
                    page: $filters->page,
                    orderBy: $filters->orderBy,
                    order: $filters->order,
                    excludedArrangementTypes: [], // Managers should see all types of requests from their subordinates even if they are personally restricted
                );

                return $this->travelRepository->getByCompany($effectiveCompanyId, $newFilters);
            } else {
                // Regular employee: only own requests
                return $this->travelRepository->getByEmployee($user->user_id, $filters);
            }
        }
    }

    public function getTravel(int $id, User $user): object
    {
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
        $travel = $this->travelRepository->findByIdAndCompany($id, $effectiveCompanyId);

        if (!$travel) {
            Log::warning('TravelService::getTravel - Travel not found', [
                'travel_id' => $id,
                'message' => 'الطلب غير موجود',
                'user_id' => $user->user_id,
            ]);
            throw new \Exception('الطلب غير موجود');
        }

        // Check if user is owner or has permission to view
        if ($user->user_type !== 'company' && $travel->employee_id !== $user->user_id) {
            // Check hierarchy permissions
            $employee = User::find($travel->employee_id);
            if ($employee && !$this->permissionService->canViewEmployeeRequests($user, $employee)) {
                Log::warning('TravelService::getTravel - Hierarchy permission denied', [
                    'travel_id' => $id,
                    'requester_id' => $user->user_id,
                    'requester_type' => $user->user_type,
                    'employee_id' => $travel->employee_id,
                    'requester_level' => $this->permissionService->getUserHierarchyLevel($user),
                    'employee_level' => $this->permissionService->getUserHierarchyLevel($employee),
                    'requester_department' => $this->permissionService->getUserDepartmentId($user),
                    'employee_department' => $this->permissionService->getUserDepartmentId($employee),
                    'message' => 'غير مسموح بعرض طلب سفر هذا الموظف',
                ]);
                throw new \Exception('غير مسموح بعرض طلب سفر هذا الموظف');
            }
        }

        // Check operation restrictions
        if ($user->user_type !== 'company') {
            $restrictedTypes = $this->permissionService->getRestrictedValues(
                $user->user_id,
                $effectiveCompanyId,
                'travel_type_'
            );

            $isOwner = $travel->employee_id === $user->user_id;
            $canOverride = $this->permissionService->canOverrideRestriction($user, $travel->employee);

            if (in_array($travel->arrangement_type, $restrictedTypes) && !$canOverride && !$isOwner) {
                Log::warning('TravelService::getTravel - Operation restriction denied', [
                    'travel_id' => $id,
                    'arrangement_type' => $travel->arrangement_type,
                    'restricted_types' => $restrictedTypes,
                ]);
                throw new \Exception('ليس لديك الصلاحية لعرض نوع السفر هذا');
            }
        }

        return $travel;
    }
}
