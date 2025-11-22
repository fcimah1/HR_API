<?php

namespace App\Services;

use App\DTOs\Asset\AssetFilterDTO;
use App\DTOs\Asset\CreateAssetDTO;
use App\DTOs\Asset\UpdateAssetDTO;
use App\DTOs\Asset\AssetResponseDTO;
use App\DTOs\Asset\BulkAssignDTO;
use App\DTOs\Asset\BulkStatusDTO;
use App\Models\Asset;
use App\Models\AssetHistory;
use App\Models\User;
use App\Services\SimplePermissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssetService
{
    public function __construct(
        private readonly SimplePermissionService $permissionService
    ) {}

    /**
     * Get paginated assets with filters (respects user permissions)
     */
    public function getPaginatedAssets(AssetFilterDTO $filters, User $user): array
    {
        $query = Asset::query();

        // Apply company filter
        if ($filters->companyId) {
            $query->forCompany($filters->companyId);
        } else {
            // Use company_name for filtering
            $query->forCompanyName($user->company_name);
        }

        // If user is not HR/Manager, only show their own assets
        if (!$this->canViewAssets($user)) {
            $query->assignedToEmployee($user->user_id);
        }

        // Apply filters
        if ($filters->hasEmployeeFilter()) {
            $query->assignedToEmployee($filters->employeeId);
        }

        if ($filters->hasCategoryFilter()) {
            $query->where('assets_category_id', $filters->categoryId);
        }

        if ($filters->hasBrandFilter()) {
            $query->where('brand_id', $filters->brandId);
        }

        if ($filters->hasStatusFilter()) {
            if ($filters->isWorking) {
                $query->working();
            } else {
                $query->nonWorking();
            }
        }

        if ($filters->hasSearchFilter()) {
            $search = $filters->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('company_asset_code', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        // Load relationships
        $query->with(['employee', 'category', 'brand']);

        // Apply sorting
        $sortBy = $filters->sortBy ?? 'name';
        $sortDirection = $filters->sortDirection ?? 'asc';
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $assets = $query->paginate($filters->perPage, ['*'], 'page', $filters->page);

        $assetDTOs = $assets->getCollection()->map(function ($asset) {
            return AssetResponseDTO::fromModel($asset);
        });

        return [
            'data' => $assetDTOs->map(fn($dto) => $dto->toArray())->toArray(),
            'pagination' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
                'from' => $assets->firstItem(),
                'to' => $assets->lastItem(),
                'has_more_pages' => $assets->hasMorePages(),
            ]
        ];
    }

    /**
     * Get asset by ID (respects user role)
     */
    public function getAssetById(int $assetId, int $companyId, User $user): ?AssetResponseDTO
    {
        $query = Asset::with(['employee', 'category', 'brand'])
            ->forCompany($companyId)
            ->where('assets_id', $assetId);

        // If user is not HR/Manager, only show their own assets
        if (!$this->canViewAssets($user)) {
            $query->assignedToEmployee($user->user_id);
        }

        $asset = $query->first();

        return $asset ? AssetResponseDTO::fromModel($asset) : null;
    }

    /**
     * Create new asset
     */
    public function createAsset(CreateAssetDTO $dto, User $user): AssetResponseDTO
    {
        return DB::transaction(function () use ($dto, $user) {
            // Check permission - asset2 for create
            if (!$this->permissionService->checkPermissionWithFallback($user, 'hr_assets', 'asset2')) {
                throw new \Exception('ليس لديك صلاحية لإنشاء الأصول');
            }
            
            $this->ensureEmployeeBelongsToCompany($dto->employeeId, $dto->companyId);

            $asset = Asset::create($dto->toArray());
            
            // Log to history
            $this->logHistory(
                $asset->assets_id,
                $asset->company_id,
                $asset->employee_id,
                AssetHistory::ACTION_UPDATED,
                $user->user_id,
                null,
                $dto->toArray(),
                'Asset created'
            );

            return AssetResponseDTO::fromModel($asset->load(['employee', 'category', 'brand']));
        });
    }
    /**
     * Ensure provided employee belongs to the company (or allow unassigned)
     */
    private function ensureEmployeeBelongsToCompany(int $employeeId, int $companyId): void
    {
        if ($employeeId <= 0) {
            return;
        }

        $exists = User::where('user_id', $employeeId)
            ->where('company_id', $companyId)
            ->exists();

        if (!$exists) {
            throw new \InvalidArgumentException('Selected employee does not belong to this company.');
        }
    }

    /**
     * Ensure assets belong to the provided company
     */
    private function ensureAssetsBelongToCompany(array $assetIds, int $companyId): void
    {
        if (empty($assetIds)) {
            return;
        }

        $uniqueIds = array_unique(array_map('intval', $assetIds));

        $count = Asset::forCompany($companyId)
            ->whereIn('assets_id', $uniqueIds)
            ->count();

        if ($count !== count($uniqueIds)) {
            throw new \InvalidArgumentException('One or more assets do not belong to this company.');
        }
    }


    /**
     * Update asset
     */
    public function updateAsset(UpdateAssetDTO $dto, int $companyId, User $user): ?AssetResponseDTO
    {
        return DB::transaction(function () use ($dto, $companyId, $user) {
            // Check permission - asset3 for edit
            if (!$this->permissionService->checkPermissionWithFallback($user, 'hr_assets', 'asset3')) {
                throw new \Exception('ليس لديك صلاحية لتعديل الأصول');
            }
            
            $asset = Asset::forCompany($companyId)
                ->where('assets_id', $dto->assetId)
                ->first();

            if (!$asset) {
                return null;
            }

            $oldValues = $asset->toArray();
            $updateData = $dto->toArray();

            if (empty($updateData)) {
                return AssetResponseDTO::fromModel($asset->load(['employee', 'category', 'brand']));
            }

            $asset->update($updateData);
            $asset->refresh();

            // Log to history
            $this->logHistory(
                $asset->assets_id,
                $asset->company_id,
                $asset->employee_id,
                AssetHistory::ACTION_UPDATED,
                $user->user_id,
                $oldValues,
                $asset->toArray(),
                'Asset updated'
            );

            return AssetResponseDTO::fromModel($asset->load(['employee', 'category', 'brand']));
        });
    }

   

    /**
     * Assign asset to employee (HR/Manager only)
     */
    public function assignAssetToEmployee(int $assetId, int $employeeId, int $companyId, User $user): ?AssetResponseDTO
    {
        return DB::transaction(function () use ($assetId, $employeeId, $companyId, $user) {
            $asset = Asset::forCompany($companyId)
                ->where('assets_id', $assetId)
                ->first();

            if (!$asset) {
                return null;
            }

            // Verify employee exists and belongs to same company
            $employee = User::where('user_id', $employeeId)
                ->where('company_id', $companyId)
                ->first();

            if (!$employee) {
                throw new \Exception('Employee not found in company');
            }

            $oldEmployeeId = $asset->employee_id;
            $asset->employee_id = $employeeId;
            $asset->save();

            // Log to history
            $this->logHistory(
                $asset->assets_id,
                $asset->company_id,
                $employeeId,
                AssetHistory::ACTION_ASSIGNED,
                $user->user_id,
                ['employee_id' => $oldEmployeeId],
                ['employee_id' => $employeeId],
                "Asset assigned to {$employee->first_name} {$employee->last_name}"
            );

            return AssetResponseDTO::fromModel($asset->load(['employee', 'category', 'brand']));
        });
    }

    /**
     * Unassign asset from employee (HR/Manager only)
     */
    public function unassignAssetFromEmployee(int $assetId, int $companyId, User $user): ?AssetResponseDTO
    {
        return DB::transaction(function () use ($assetId, $companyId, $user) {
            $asset = Asset::forCompany($companyId)
                ->where('assets_id', $assetId)
                ->first();

            if (!$asset) {
                return null;
            }

            $oldEmployeeId = $asset->employee_id;
            $asset->employee_id = 0;
            $asset->save();

            // Log to history
            $this->logHistory(
                $asset->assets_id,
                $asset->company_id,
                0,
                AssetHistory::ACTION_UNASSIGNED,
                $user->user_id,
                ['employee_id' => $oldEmployeeId],
                ['employee_id' => 0],
                'Asset unassigned'
            );

            return AssetResponseDTO::fromModel($asset->load(['employee', 'category', 'brand']));
        });
    }

    /**
     * Report asset for fixing (all employees can report their own assets)
     */
    public function reportAssetForFixing(int $assetId, int $companyId, User $user): ?AssetResponseDTO
    {
        return DB::transaction(function () use ($assetId, $companyId, $user) {
            $asset = Asset::forCompany($companyId)
                ->where('assets_id', $assetId)
                ->first();

            if (!$asset) {
                return null;
            }

            // Verify asset is assigned to this employee
            if ($asset->employee_id !== $user->user_id) {
                throw new \Exception('You can only report assets assigned to you');
            }

            $oldStatus = $asset->is_working;
            $asset->is_working = 0; // Mark as non-working
            $asset->save();

            // Log to history
            $this->logHistory(
                $asset->assets_id,
                $asset->company_id,
                $user->user_id,
                AssetHistory::ACTION_REPORTED,
                $user->user_id,
                ['is_working' => $oldStatus],
                ['is_working' => 0],
                'Asset reported for fixing'
            );

            return AssetResponseDTO::fromModel($asset->load(['employee', 'category', 'brand']));
        });
    }

    /**
     * Get assets by employee
     */
    public function getAssetsByEmployee(int $employeeId, int $companyId): array
    {
        $assets = Asset::with(['employee', 'category', 'brand'])
            ->forCompany($companyId)
            ->assignedToEmployee($employeeId)
            ->get();

        return $assets->map(function ($asset) {
            return AssetResponseDTO::fromModel($asset)->toArray();
        })->toArray();
    }

    /**
     * Get asset history (Manager only)
     */
    public function getAssetHistory(int $assetId, int $companyId): array
    {
        $history = AssetHistory::with(['changedBy', 'employee'])
            ->forAsset($assetId)
            ->forCompany($companyId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $history->map(function ($entry) {
            return [
                'id' => $entry->id,
                'action' => $entry->action,
                'changed_by' => $entry->changedBy ? [
                    'user_id' => $entry->changedBy->user_id,
                    'name' => $entry->changedBy->first_name . ' ' . $entry->changedBy->last_name,
                ] : null,
                'employee' => $entry->employee ? [
                    'user_id' => $entry->employee->user_id,
                    'name' => $entry->employee->first_name . ' ' . $entry->employee->last_name,
                ] : null,
                'old_value' => $entry->old_value,
                'new_value' => $entry->new_value,
                'notes' => $entry->notes,
                'created_at' => $entry->created_at,
            ];
        })->toArray();
    }

    /**
     * Get asset statistics
     */
    public function getAssetStats(int $companyId): array
    {
        $total = Asset::forCompany($companyId)->count();
        $assigned = Asset::forCompany($companyId)->where('employee_id', '>', 0)->count();
        $unassigned = Asset::forCompany($companyId)->where('employee_id', 0)->count();
        $working = Asset::forCompany($companyId)->working()->count();
        $nonWorking = Asset::forCompany($companyId)->nonWorking()->count();

        // By category
        $byCategory = Asset::forCompany($companyId)
            ->join('ci_erp_constants', 'ci_assets.assets_category_id', '=', 'ci_erp_constants.constants_id')
            ->where('ci_erp_constants.type', 'assets_category')
            ->selectRaw('ci_erp_constants.category_name, COUNT(*) as count')
            ->groupBy('ci_erp_constants.category_name')
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->category_name,
                    'count' => (int) $item->count,
                ];
            })
            ->toArray();

        return [
            'total' => $total,
            'assigned' => $assigned,
            'unassigned' => $unassigned,
            'working' => $working,
            'non_working' => $nonWorking,
            'by_category' => $byCategory,
        ];
    }

    /**
     * Bulk assign assets to employee
     */
    public function bulkAssignAssets(BulkAssignDTO $dto, User $user): array
    {
        return DB::transaction(function () use ($dto, $user) {
            $this->ensureEmployeeBelongsToCompany($dto->employeeId, $dto->companyId);
            $this->ensureAssetsBelongToCompany($dto->assetIds, $dto->companyId);

            $results = [
                'success' => [],
                'failed' => [],
            ];

            foreach ($dto->assetIds as $assetId) {
                try {
                    $result = $this->assignAssetToEmployee($assetId, $dto->employeeId, $dto->companyId, $user);
                    if ($result) {
                        $results['success'][] = $assetId;
                    } else {
                        $results['failed'][] = $assetId;
                    }
                } catch (\Exception $e) {
                    Log::error("Bulk assign failed for asset {$assetId}", ['error' => $e->getMessage()]);
                    $results['failed'][] = $assetId;
                }
            }

            return $results;
        });
    }

    /**
     * Bulk unassign assets
     */
    public function bulkUnassignAssets(array $assetIds, int $companyId, User $user): array
    {
        return DB::transaction(function () use ($assetIds, $companyId, $user) {
            $this->ensureAssetsBelongToCompany($assetIds, $companyId);

            $results = [
                'success' => [],
                'failed' => [],
            ];

            foreach ($assetIds as $assetId) {
                try {
                    $result = $this->unassignAssetFromEmployee($assetId, $companyId, $user);
                    if ($result) {
                        $results['success'][] = $assetId;
                    } else {
                        $results['failed'][] = $assetId;
                    }
                } catch (\Exception $e) {
                    Log::error("Bulk unassign failed for asset {$assetId}", ['error' => $e->getMessage()]);
                    $results['failed'][] = $assetId;
                }
            }

            return $results;
        });
    }

    /**
     * Bulk update asset status
     */
    public function bulkUpdateStatus(BulkStatusDTO $dto, User $user): array
    {
        return DB::transaction(function () use ($dto, $user) {
            $this->ensureAssetsBelongToCompany($dto->assetIds, $dto->companyId);

            $results = [
                'success' => [],
                'failed' => [],
            ];

            foreach ($dto->assetIds as $assetId) {
                try {
                    $asset = Asset::forCompany($dto->companyId)
                        ->where('assets_id', $assetId)
                        ->first();

                    if ($asset) {
                        $oldStatus = $asset->is_working;
                        $asset->is_working = $dto->isWorking ? 1 : 0;
                        $asset->save();

                        // Log to history
                        $this->logHistory(
                            $asset->assets_id,
                            $asset->company_id,
                            $asset->employee_id,
                            AssetHistory::ACTION_STATUS_CHANGED,
                            $user->user_id,
                            ['is_working' => $oldStatus],
                            ['is_working' => $asset->is_working],
                            'Bulk status update'
                        );

                        $results['success'][] = $assetId;
                    } else {
                        $results['failed'][] = $assetId;
                    }
                } catch (\Exception $e) {
                    Log::error("Bulk status update failed for asset {$assetId}", ['error' => $e->getMessage()]);
                    $results['failed'][] = $assetId;
                }
            }

            return $results;
        });
    }

    /**
     * Permission checks using legacy permission system
     */
    public function canViewAssets(User $user): bool
    {
        // Check parent permission (hr_assets) or sub-permission (asset1)
        return $this->permissionService->checkPermissionWithFallback($user, 'hr_assets', 'asset1');
    }

    public function canManageAssets(User $user): bool
    {
        // Check parent permission (hr_assets) or sub-permission (asset2 for create)
        return $this->permissionService->checkPermissionWithFallback($user, 'hr_assets', 'asset2');
    }

    public function canAssignAssets(User $user): bool
    {
        // Check parent permission (hr_assets) for assignment operations
        return $this->permissionService->checkPermission($user, 'hr_assets');
    }

    public function canViewAssetHistory(User $user): bool
    {
        // Check parent permission (hr_assets) for viewing history
        return $this->permissionService->checkPermission($user, 'hr_assets');
    }

    public function canReportAsset(User $user): bool
    {
        // All authenticated employees can report their own assets
        return true;
    }

    /**
     * Log asset history
     */
    private function logHistory(
        int $assetId,
        int $companyId,
        int $employeeId,
        string $action,
        int $changedBy,
        ?array $oldValue = null,
        ?array $newValue = null,
        ?string $notes = null
    ): void {
        AssetHistory::create([
            'asset_id' => $assetId,
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'action' => $action,
            'changed_by' => $changedBy,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

