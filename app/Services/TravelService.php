<?php

namespace App\Services;

use App\DTOs\Travel\CreateTravelDTO;
use App\DTOs\Travel\UpdateTravelDTO;
use App\Http\Requests\Travel\UpdateTravelStatusRequest;
use App\Models\User;
use App\Repository\Interface\TravelRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\SimplePermissionService;

class TravelService
{
    protected $travelRepository;
    protected $permissionService;

    public function __construct(TravelRepositoryInterface $travelRepository, SimplePermissionService $permissionService)
    {
        $this->travelRepository = $travelRepository;
        $this->permissionService = $permissionService;
    }

    public function createTravel(CreateTravelDTO $dto): object
    {
        return DB::transaction(function () use ($dto) {
            Log::info('TravelService::createTravel - Transaction started', ['employee_id' => $dto->employee_id]);

            // Check for overlapping travel dates
            Log::info('TravelService::createTravel - Checking for overlaps', [
                'employee_id' => $dto->employee_id,
                'start_date' => $dto->start_date,
                'end_date' => $dto->end_date
            ]);

            if ($this->travelRepository->hasOverlappingTravel($dto->employee_id, $dto->start_date, $dto->end_date)) {
                Log::warning('TravelService::createTravel - Overlap detected!');
                throw new \Exception('يوجد طلب سفر آخر لنفس الموظف في نفس الفترة الزمنية أو فترة متداخلة معها');
            }

            $travel = $this->travelRepository->create($dto);

            Log::info('TravelService::createTravel - Transaction committed', ['travel_id' => $travel->travel_id]);
            return $travel;
        });
    }

    public function updateTravel(int $id, UpdateTravelDTO $dto, User $user): object
    {
        return DB::transaction(function () use ($id, $dto, $user) {
            Log::info('TravelService::updateTravel - Transaction started', ['travel_id' => $id]);

            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $travel = $this->travelRepository->findByIdAndCompany($id, $effectiveCompanyId);

            if (!$travel) {
                throw new \Exception('Travel request not found or access denied');
            }

            // Check permissions (only owner or company can update, and usually only if pending)
            $isOwner = $travel->employee_id === $user->user_id;
            $isCompany = $user->user_type === 'company'; // Or check role

            if (!$isOwner && !$isCompany) {
                throw new \Exception('Unauthorized to update this travel request');
            }

            // If owner, can only update if pending (status 0)
            if ($isOwner && $travel->status != 0) {
                throw new \Exception('Cannot update travel request after it has been processed');
            }

            // Check for overlapping travel dates (if dates are being updated)
            $startDate = $dto->start_date ?? $travel->start_date;
            $endDate = $dto->end_date ?? $travel->end_date;

            if ($this->travelRepository->hasOverlappingTravel($travel->employee_id, $startDate, $endDate, $id)) {
                throw new \Exception('يوجد طلب سفر آخر لنفس الموظف في نفس الفترة الزمنية أو فترة متداخلة معها');
            }

            $this->travelRepository->update($travel, $dto);

            Log::info('TravelService::updateTravel - Transaction committed', ['travel_id' => $id]);
            return $travel;
        });
    }

    public function cancelTravel(int $id, User $user): bool
    {
        return DB::transaction(function () use ($id, $user) {
            Log::info('TravelService::cancelTravel - Transaction started', ['travel_id' => $id]);

            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $travel = $this->travelRepository->findByIdAndCompany($id, $effectiveCompanyId);

            if (!$travel) {
                throw new \Exception('Travel request not found');
            }

            // Permission check
            $isOwner = $travel->employee_id === $user->user_id;
            $isCompany = $user->user_type === 'company';

            if (!$isOwner && !$isCompany) {
                throw new \Exception('Unauthorized to delete this travel request');
            }

            if ($isOwner && $travel->status != 0) {
                throw new \Exception('Cannot delete travel request after it has been processed');
            }

            $this->travelRepository->cancel($id);

            Log::info('TravelService::cancelTravel - Transaction committed', ['travel_id' => $id]);
            return true;
        });
    }

    public function approveTravel(int $id, UpdateTravelStatusRequest $request, User $user): object
    {
        return DB::transaction(function () use ($id, $request, $user) {
            Log::info('TravelService::approveTravel - Transaction started', ['travel_id' => $id]);

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

            Log::info('TravelService::approveTravel - Transaction committed', ['travel_id' => $id]);
            return $travel;
        });
    }

    public function rejectTravel(int $id, UpdateTravelStatusRequest $request, User $user): object
    {
        return DB::transaction(function () use ($id, $request, $user) {
            Log::info('TravelService::rejectTravel - Transaction started', ['travel_id' => $id]);

            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $travel = $this->travelRepository->findByIdAndCompany($id, $effectiveCompanyId);

            if (!$travel) {
                throw new \Exception('الطلب غير موجود');
            }

            if ($travel->status !== 0) {
                throw new \Exception('تم رفض هذا الطلب مسبقاً أو تم الموافقة عليه');
            }

            $travel = $this->travelRepository->reject($id);

            Log::info('TravelService::rejectTravel - Transaction committed', ['travel_id' => $id]);
            return $travel;
        });
    }

    public function getTravels(User $user, array $filters = [])
    {
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

        if ($user->user_type === 'company' || $this->permissionService->checkPermission($user, 'view_all_travels')) {
            return $this->travelRepository->getByCompany($effectiveCompanyId);
        } else {
            return $this->travelRepository->getByEmployee($user->user_id);
        }
    }

    public function getTravel(int $id, User $user): object
    {
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
        $travel = $this->travelRepository->findByIdAndCompany($id, $effectiveCompanyId);

        if (!$travel) {
            throw new \Exception('Travel request not found');
        }

        // Check if user is owner or has permission to view
        if ($user->user_type !== 'company' && $travel->employee_id !== $user->user_id) {
            // Add more granular permission checks here if needed (e.g. manager view)
            throw new \Exception('Unauthorized to view this travel request');
        }

        return $travel;
    }
}
