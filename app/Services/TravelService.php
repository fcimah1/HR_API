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

class TravelService
{
    public function __construct(
        protected TravelRepositoryInterface $travelRepository,
        protected SimplePermissionService $permissionService,
        protected NotificationService $notificationService,
        protected ApprovalWorkflowService $approvalWorkflow,
    ) {}

    public function getTravelEnums(): array
    {
        return [
            'statuses' => StringStatusEnum::toArray(),
            'travel_modes' => TravelModeEnum::toArray(),
        ];
    }

    public function createTravel(CreateTravelDTO $dto): object
    {
        return DB::transaction(function () use ($dto) {

            if ($this->travelRepository->hasOverlappingTravel($dto->employee_id, $dto->start_date, $dto->end_date)) {
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
                throw new \Exception('الطلب غير موجود');
            }

            // Check permissions (only owner or company can update, and usually only if pending)
            $isOwner = $travel->employee_id === $user->user_id;
            $isCompany = $user->user_type === 'company'; // Or check role

            if (!$isOwner && !$isCompany) {
                throw new \Exception('غير مسموح بتحديث طلب سفر الموظف');
            }

            if ($travel->status == 1) {
                throw new \Exception(' لا يمكن تحديث طلب سفر بعد الموافقة عليه');
            }

            if ($travel->status == 2) {
                throw new \Exception(' لا يمكن تحديث طلب سفر بعد رفضه');
            }

            // Check for overlapping travel dates (if dates are being updated)
            $startDate = $dto->start_date ?? $travel->start_date;
            $endDate = $dto->end_date ?? $travel->end_date;

            if ($this->travelRepository->hasOverlappingTravel($travel->employee_id, $startDate, $endDate, $id)) {
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
                throw new \Exception('الطلب غير موجود');
            }

            // Permission check
            $isOwner = $travel->employee_id === $user->user_id;
            $isCompany = $user->user_type === 'company';

            if (!$isOwner && !$isCompany) {
                throw new \Exception('غير مسموح بحذف طلب سفر الموظف');
            }

            // Only pending requests can be cancelled
            if ($travel->status == 1) {
                throw new \Exception('لا يمكن حذف طلب سفر تم الموافقة عليه');
            }

            if ($travel->status == 2) {
                throw new \Exception('لا يمكن حذف طلب سفر تم رفضه');
            }

            $this->travelRepository->cancel($id);

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
                throw new \Exception('الطلب غير موجود');
            }

            if ($travel->status !== 0) {
                throw new \Exception('تم الموافقة على هذا الطلب مسبقاً أو تم رفضه');
            }

            $travel = $this->travelRepository->approve($id);

            // Send approval notification
            $this->notificationService->sendApprovalNotification(
                'travel_settings',
                (string)$travel->travel_id,
                $effectiveCompanyId,
                StringStatusEnum::APPROVED->value,
                $user->user_id,  // Approver ID
                null,
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
                        remarks: null
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
                throw new \Exception('الطلب غير موجود');
            }

            if ($travel->status !== 0) {
                throw new \Exception('تم رفض هذا الطلب مسبقاً أو تم الموافقة عليه');
            }

            $travel = $this->travelRepository->reject($id);

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
            return $this->travelRepository->getByEmployee($user->user_id, $filters);
        }
    }

    public function getTravel(int $id, User $user): object
    {
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
        $travel = $this->travelRepository->findByIdAndCompany($id, $effectiveCompanyId);

        if (!$travel) {
            throw new \Exception('الطلب غير موجود');
        }

        // Check if user is owner or has permission to view
        if ($user->user_type !== 'company' && $travel->employee_id !== $user->user_id) {
            // Add more granular permission checks here if needed (e.g. manager view)
            throw new \Exception('غير مسموح بعرض طلب سفر الموظف');
        }

        return $travel;
    }
}
