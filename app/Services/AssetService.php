<?php

namespace App\Services;

use App\DTOs\Asset\AssetFilterDTO;
use App\DTOs\Asset\CreateAssetDTO;
use App\DTOs\Asset\UpdateAssetDTO;
use App\Models\Asset;
use App\Repository\Interface\AssetRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class AssetService
{
    protected $assetRepository;
    protected $fileUploadService;

    public function __construct(
        AssetRepositoryInterface $assetRepository,
        FileUploadService $fileUploadService
    ) {
        $this->assetRepository = $assetRepository;
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Get Assets with pagination.
     */
    public function getAssets(int $companyId, AssetFilterDTO $filterDTO): LengthAwarePaginator
    {
        return $this->assetRepository->getAssets(
            $companyId,
            $filterDTO->toArray(),
            $filterDTO->perPage
        );
    }

    /**
     * Get Asset Details.
     */
    public function getAsset(int $id, int $companyId): ?Asset
    {
        return $this->assetRepository->find($id, $companyId);
    }

    /**
     * Create a new Asset.
     */
    public function createAsset(CreateAssetDTO $dto): Asset
    {
        $data = $dto->toArray();

        // Handle Image Upload
        if ($dto->image) {
            $employeeId = $dto->employeeId ?? 0;
            $uploadResult = $this->fileUploadService->uploadDocument($dto->image, $employeeId, 'asset_image', 'asset');

            if ($uploadResult) {
                $data['asset_image'] = $uploadResult['filename'];
            }
        }

        $data['created_at'] = now();

        return $this->assetRepository->create($data);
    }

    /**
     * Update an Asset.
     */
    public function updateAsset(UpdateAssetDTO $dto, int $companyId): ?Asset
    {
        $asset = $this->assetRepository->find($dto->assetId, $companyId);

        if (!$asset) {
            return null;
        }

        $data = $dto->toArray();

        // Handle Image Upload
        if ($dto->image) {
            // Delete old image if exists? (Optional but good practice)
            // if ($asset->asset_image) { ... }

            $employeeId = $dto->employeeId ?? ($asset->employee_id ?? 0);
            $uploadResult = $this->fileUploadService->uploadDocument($dto->image, $employeeId, 'asset_image', 'asset');

            if ($uploadResult) {
                $data['asset_image'] = $uploadResult['filename'];
            }
        }

        return $this->assetRepository->update($asset, $data);
    }

    /**
     * Delete an Asset.
     */
    public function deleteAsset(int $id, int $companyId): bool
    {
        $asset = $this->assetRepository->find($id, $companyId);

        if (!$asset) {
            return false;
        }

        return $this->assetRepository->delete($asset);
    }
}
