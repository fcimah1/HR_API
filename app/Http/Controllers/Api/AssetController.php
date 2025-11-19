<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AssetService;
use App\DTOs\Asset\AssetFilterDTO;
use App\DTOs\Asset\CreateAssetDTO;
use App\DTOs\Asset\UpdateAssetDTO;
use App\DTOs\Asset\BulkAssignDTO;
use App\DTOs\Asset\BulkStatusDTO;
use App\Http\Requests\Asset\CreateAssetRequest;
use App\Http\Requests\Asset\UpdateAssetRequest;
use App\Http\Requests\Asset\AssignAssetRequest;
use App\Http\Requests\Asset\ReportFixingRequest;
use App\Http\Requests\Asset\BulkAssignRequest;
use App\Http\Requests\Asset\BulkStatusRequest;
use App\Models\ErpConstant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Asset Management",
 *     description="Employee assets management endpoints"
 * )
 */
class AssetController extends Controller
{
    public function __construct(
        private readonly AssetService $assetService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/assets",
     *     summary="List assets with filters",
     *     tags={"Asset Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by name, code, or serial number",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         description="Filter by employee ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by category ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="brand_id",
     *         in="query",
     *         description="Filter by brand ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="is_working",
     *         in="query",
     *         description="Filter by working status",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Assets retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="pagination", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Service handles role-based filtering
        $filters = AssetFilterDTO::fromRequest([
            ...$request->all(),
            'company_id' => $user->company_id
        ]);

        $result = $this->assetService->getPaginatedAssets($filters, $user);

        return response()->json([
            'success' => true,
            ...$result
        ]);
    }
    

    /**
     * @OA\Get(
     *     path="/api/assets/{id}",
     *     summary="Get asset details",
     *     tags={"Asset Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Asset ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Asset retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Asset not found"
     *     )
     * )
     */
    public function show(Request $request, $id)
    {
        $user = Auth::user();
        
        $asset = $this->assetService->getAssetById((int) $id, $user->company_id, $user);

        if (!$asset) {
            return response()->json([
                'success' => false,
                'message' => 'Asset not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $asset->toArray()
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/assets",
     *     summary="Create new asset",
     *     tags={"Asset Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"assets_category_id","brand_id","name"},
     *             @OA\Property(property="assets_category_id", type="integer", example=1),
     *             @OA\Property(property="brand_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Dell Laptop"),
     *             @OA\Property(property="company_asset_code", type="string", example="LAP001"),
     *             @OA\Property(property="purchase_date", type="string", example="2024-01-15"),
     *             @OA\Property(property="invoice_number", type="string", example="INV-2024-001"),
     *             @OA\Property(property="manufacturer", type="string", example="Dell"),
     *             @OA\Property(property="serial_number", type="string", example="DL2024001"),
     *             @OA\Property(property="warranty_end_date", type="string", example="2025-12-31"),
     *             @OA\Property(property="asset_note", type="string", example="Office laptop"),
     *             @OA\Property(property="is_working", type="boolean", example=true),
     *             @OA\Property(property="employee_id", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Asset created successfully"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions"
     *     )
     * )
     */
    public function store(CreateAssetRequest $request)
    {
        $user = Auth::user();
        
        if (!$this->assetService->canManageAssets($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create assets'
            ], 403);
        }

        try {
            $dto = CreateAssetDTO::fromRequest([
                ...$request->validated(),
                'company_id' => $user->company_id
            ]);

            $asset = $this->assetService->createAsset($dto, $user);

            return response()->json([
                'success' => true,
                'message' => 'Asset created successfully',
                'data' => $asset->toArray()
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create asset: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/assets/{id}",
     *     summary="Update asset",
     *     tags={"Asset Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="is_working", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Asset updated successfully"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function update(UpdateAssetRequest $request, $id)
    {
        $user = Auth::user();
        
        if (!$this->assetService->canManageAssets($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update assets'
            ], 403);
        }

        try {
            $dto = UpdateAssetDTO::fromRequest((int) $id, $request->validated());
            $asset = $this->assetService->updateAsset($dto, $user->company_id, $user);

            if (!$asset) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asset not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Asset updated successfully',
                'data' => $asset->toArray()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update asset: ' . $e->getMessage()
            ], 500);
        }
    }

    
    /**
     * @OA\Post(
     *     path="/api/assets/{id}/assign",
     *     summary="Assign asset to employee",
     *     tags={"Asset Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id"},
     *             @OA\Property(property="employee_id", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Asset assigned successfully"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function assign(AssignAssetRequest $request, $id)
    {
        $user = Auth::user();
        
        if (!$this->assetService->canAssignAssets($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to assign assets'
            ], 403);
        }

        try {
            $asset = $this->assetService->assignAssetToEmployee(
                (int) $id,
                (int) $request->employee_id,
                $user->company_id,
                $user
            );

            if (!$asset) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asset not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Asset assigned successfully',
                'data' => $asset->toArray()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/assets/{id}/unassign",
     *     summary="Unassign asset from employee",
     *     tags={"Asset Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Asset unassigned successfully"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function unassign(Request $request, $id)
    {
        $user = Auth::user();
        
        if (!$this->assetService->canAssignAssets($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to unassign assets'
            ], 403);
        }

        try {
            $asset = $this->assetService->unassignAssetFromEmployee(
                (int) $id,
                $user->company_id,
                $user
            );

            if (!$asset) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asset not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Asset unassigned successfully',
                'data' => $asset->toArray()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/assets/{id}/report-fixing",
     *     summary="Report asset for fixing",
     *     tags={"Asset Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="string", example="Screen is broken")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Asset reported successfully"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function reportFixing(ReportFixingRequest $request, $id)
    {
        $user = Auth::user();
        
        try {
            $asset = $this->assetService->reportAssetForFixing(
                (int) $id,
                $user->company_id,
                $user
            );

            if (!$asset) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asset not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Asset reported for fixing successfully',
                'data' => $asset->toArray()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/assets/employee/{employeeId}",
     *     summary="Get assets assigned to specific employee",
     *     tags={"Asset Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="employeeId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Assets retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function getByEmployee(Request $request, $employeeId)
    {
        $user = Auth::user();
        
        if (!$this->assetService->canViewAssets($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view assets'
            ], 403);
        }

        $assets = $this->assetService->getAssetsByEmployee((int) $employeeId, $user->company_id);

        return response()->json([
            'success' => true,
            'data' => $assets
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/assets/my-assets",
     *     summary="Get current user's assigned assets",
     *     tags={"Asset Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Assets retrieved successfully"
     *     )
     * )
     */
    public function myAssets(Request $request)
    {
        $user = Auth::user();
        
        $assets = $this->assetService->getAssetsByEmployee($user->user_id, $user->company_id);

        return response()->json([
            'success' => true,
            'data' => $assets
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/assets/{id}/history",
     *     summary="Get asset history/audit log",
     *     tags={"Asset Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="History retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Manager only"
     *     )
     * )
     */
    public function history(Request $request, $id)
    {
        $user = Auth::user();
        
        if (!$this->assetService->canViewAssetHistory($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view asset history'
            ], 403);
        }

        $history = $this->assetService->getAssetHistory((int) $id, $user->company_id);

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/assets/categories",
     *     summary="Get asset categories for company",
     *     tags={"Asset Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Categories retrieved successfully"
     *     )
     * )
     */
    public function categories(Request $request)
    {
        $user = Auth::user();
        
        $categories = ErpConstant::getAssetCategoriesByCompanyName($user->company_name);

        return response()->json([
            'success' => true,
            'data' => $categories->map(function ($category) {
                return [
                    'id' => $category->constants_id,
                    'name' => $category->category_name,
                ];
            })->toArray()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/assets/brands",
     *     summary="Get asset brands for company",
     *     tags={"Asset Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Brands retrieved successfully"
     *     )
     * )
     */
    public function brands(Request $request)
    {
        $user = Auth::user();
        
        $brands = ErpConstant::getAssetBrandsByCompanyName($user->company_name);

        return response()->json([
            'success' => true,
            'data' => $brands->map(function ($brand) {
                return [
                    'id' => $brand->constants_id,
                    'name' => $brand->category_name,
                ];
            })->toArray()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/assets/stats",
     *     summary="Get asset statistics",
     *     tags={"Asset Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function stats(Request $request)
    {
        $user = Auth::user();
        
        if (!$this->assetService->canViewAssets($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view asset statistics'
            ], 403);
        }

        $stats = $this->assetService->getAssetStats($user->company_id);

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/assets/bulk-assign",
     *     summary="Bulk assign assets to employee",
     *     tags={"Asset Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"asset_ids","employee_id"},
     *             @OA\Property(property="asset_ids", type="array", @OA\Items(type="integer"), example={1,2,3}),
     *             @OA\Property(property="employee_id", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bulk assignment completed"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function bulkAssign(BulkAssignRequest $request)
    {
        $user = Auth::user();
        
        if (!$this->assetService->canAssignAssets($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to assign assets'
            ], 403);
        }

        try {
            $dto = BulkAssignDTO::fromRequest([
                ...$request->validated(),
                'company_id' => $user->company_id
            ]);

            $results = $this->assetService->bulkAssignAssets($dto, $user);

            return response()->json([
                'success' => true,
                'message' => 'Bulk assignment completed',
                'data' => $results
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/assets/bulk-unassign",
     *     summary="Bulk unassign assets",
     *     tags={"Asset Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"asset_ids"},
     *             @OA\Property(property="asset_ids", type="array", @OA\Items(type="integer"), example={1,2,3})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bulk unassignment completed"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function bulkUnassign(Request $request)
    {
        $user = Auth::user();
        
        if (!$this->assetService->canAssignAssets($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to unassign assets'
            ], 403);
        }

        $request->validate([
            'asset_ids' => 'required|array|min:1',
            'asset_ids.*' => 'integer|exists:ci_assets,assets_id',
        ]);

        try {
            $results = $this->assetService->bulkUnassignAssets(
                $request->asset_ids,
                $user->company_id,
                $user
            );

            return response()->json([
                'success' => true,
                'message' => 'Bulk unassignment completed',
                'data' => $results
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/assets/bulk-status",
     *     summary="Bulk update asset status",
     *     tags={"Asset Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"asset_ids","is_working"},
     *             @OA\Property(property="asset_ids", type="array", @OA\Items(type="integer"), example={1,2,3}),
     *             @OA\Property(property="is_working", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bulk status update completed"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function bulkStatus(BulkStatusRequest $request)
    {
        $user = Auth::user();
        
        if (!$this->assetService->canManageAssets($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update asset status'
            ], 403);
        }

        try {
            $dto = BulkStatusDTO::fromRequest([
                ...$request->validated(),
                'company_id' => $user->company_id
            ]);

            $results = $this->assetService->bulkUpdateStatus($dto, $user);

            return response()->json([
                'success' => true,
                'message' => 'Bulk status update completed',
                'data' => $results
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}

