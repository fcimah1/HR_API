<?php

namespace App\Services;

use App\DTOs\Award\CreateAwardDTO;
use App\DTOs\Award\UpdateAwardDTO;
use App\Models\Award;
use App\Repository\Interface\AwardRepositoryInterface;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class AwardService
{
    protected $awardRepository;
    protected $fileUploadService;

    public function __construct(
        AwardRepositoryInterface $awardRepository,
        FileUploadService $fileUploadService
    ) {
        $this->awardRepository = $awardRepository;
        $this->fileUploadService = $fileUploadService;
    }

    public function getAwards(int $companyId, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->awardRepository->getAwards($companyId, $filters, $perPage);
    }

    public function getAward(int $id, int $companyId): ?Award
    {
        return $this->awardRepository->find($id, $companyId);
    }

    public function createAward(CreateAwardDTO $dto): ?Award
    {
        try{
            $data = $dto->toArray();

            // Handle Image Upload
            if ($dto->file) {
                // Using logic similar to AssetService
                $uploadResult = $this->fileUploadService->uploadDocument($dto->file, $dto->employeeId, 'awards', 'award');

                if ($uploadResult) {
                    $data['award_file'] = $uploadResult['filename'];
                }
            }

            // Manual timestamp if needed, similar to Assets?
            // Award model uses $timestamps = false, so let's set created_at
            $data['created_at'] = now();

            // Also map DTO 'giftItem' to 'award_information' if they are meant to be the same, 
            // OR better yet relying on toArray() to have correct mapping. 
            // In CreateAwardDTO I mapped to both for safety if logic was unclear, but let's be cleaner.
            // It seems 'gift_item' and 'award_information' are separate columns in the updated model.
            // In DTO I will just use what is passed.

            return $this->awardRepository->create($data);

        }catch(Exception $e){
            Log::error('AwardService@createAward: Error creating award', [
                'message' => $e->getMessage(),
                'dto' => $dto,
            ]);
            return null;
        }
    }

    public function updateAward(UpdateAwardDTO $dto, int $companyId): ?Award
    {
        try{
            $award = $this->awardRepository->find($dto->awardId, $companyId);

            if (!$award) {
                return null;
            }

            $data = $dto->toArray();

            if ($dto->file) {
                $employeeId = $dto->employeeId ?? $award->employee_id;
                $uploadResult = $this->fileUploadService->uploadDocument($dto->file, $employeeId, 'awards', 'award');

                if ($uploadResult) {
                    $data['award_file'] = $uploadResult['filename'];
                }
            }

            return $this->awardRepository->update($award, $data);

        }catch(Exception $e){
            Log::error('AwardService@updateAward: Error updating award', [
                'message' => $e->getMessage(),
                'award_id' => $dto->awardId,
                'company_id' => $companyId,
                'data' => $data,
            ]);
            return null;
        }
    }

    public function deleteAward(int $id, int $companyId): bool
    {
        try{
            $award = $this->awardRepository->find($id, $companyId);

            if (!$award) {
                return false;
            }

            return $this->awardRepository->delete($award);
        }catch(Exception $e){
            Log::error('AwardService@deleteAward: Error deleting award', [
                'message' => $e->getMessage(),
                'award_id' => $id,
                'company_id' => $companyId,
            ]);
            return false;
        }
    }
}
