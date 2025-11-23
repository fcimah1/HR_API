<?php

namespace App\Services;

use App\DTOs\TravelType\CreateTravelTypeDTO;
use App\DTOs\TravelType\UpdateTravelTypeDTO;
use App\Models\User;
use App\Repository\Interface\TravelTypeRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TravelTypeService
{
    protected $travelTypeRepository;
    protected $permissionService;

    public function __construct(TravelTypeRepositoryInterface $travelTypeRepository, SimplePermissionService $permissionService)
    {
        $this->travelTypeRepository = $travelTypeRepository;
        $this->permissionService = $permissionService;
    }

    public function createTravelType(CreateTravelTypeDTO $dto): object
    {
        return DB::transaction(function () use ($dto) {
            Log::info('TravelTypeService::createTravelType - Transaction started');

            $travelType = $this->travelTypeRepository->create($dto);

            Log::info('TravelTypeService::createTravelType - Transaction committed', ['id' => $travelType->constants_id]);
            return $travelType;
        });
    }

    public function updateTravelType(int $id, UpdateTravelTypeDTO $dto, User $user): object
    {
        return DB::transaction(function () use ($id, $dto, $user) {
            Log::info('TravelTypeService::updateTravelType - Transaction started', ['id' => $id]);

            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $travelType = $this->travelTypeRepository->findByIdAndCompany($id, $effectiveCompanyId);

            if (!$travelType) {
                throw new \Exception('نوع السفر غير موجود');
            }

            $this->travelTypeRepository->update($travelType, $dto);

            Log::info('TravelTypeService::updateTravelType - Transaction committed', ['id' => $id]);
            return $travelType;
        });
    }

    public function deleteTravelType(int $id, User $user): bool
    {
        return DB::transaction(function () use ($id, $user) {
            Log::info('TravelTypeService::deleteTravelType - Transaction started', ['id' => $id]);

            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $travelType = $this->travelTypeRepository->findByIdAndCompany($id, $effectiveCompanyId);

            if (!$travelType) {
                throw new \Exception('نوع السفر غير موجود');
            }

            $this->travelTypeRepository->delete($id);

            Log::info('TravelTypeService::deleteTravelType - Transaction committed', ['id' => $id]);
            return true;
        });
    }

    public function getTravelTypes(User $user)
    {
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
        return $this->travelTypeRepository->getByCompany($effectiveCompanyId);
    }

    public function getTravelType(int $id, User $user): object
    {
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
        $travelType = $this->travelTypeRepository->findByIdAndCompany($id, $effectiveCompanyId);

        if (!$travelType) {
            throw new \Exception('نوع السفر غير موجود');
        }

        return $travelType;
    }

    public function searchTravelTypes(User $user, string $query)
    {
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
        return $this->travelTypeRepository->search($effectiveCompanyId, $query);
    }
}
