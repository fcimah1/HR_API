<?php

namespace App\Http\Controllers\Api;

use App\DTOs\TravelType\CreateTravelTypeDTO;
use App\DTOs\TravelType\UpdateTravelTypeDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\TravelType\CreateTravelTypeRequest;
use App\Http\Requests\TravelType\UpdateTravelTypeRequest;
use App\Services\SimplePermissionService;
use App\Services\TravelTypeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TravelTypeController extends Controller
{
    protected $travelTypeService;
    protected $permissionService;

    public function __construct(TravelTypeService $travelTypeService, SimplePermissionService $permissionService)
    {
        $this->travelTypeService = $travelTypeService;
        $this->permissionService = $permissionService;
    }

    /**
     * @OA\Get(
     *     path="/api/travel-types",
     *     summary="Get list of travel types",
     *     tags={"Travel Type"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index()
    {
        try {
            $user = Auth::user();
            $hasPermission = $this->permissionService->checkPermission($user, 'travel_type1');
            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مسموح بعرض أنواع السفر'
                ], 403);
            }
            $travelTypes = $this->travelTypeService->getTravelTypes($user);
            return response()->json(['success' => true, 'message' => 'تم الحصول على أنواع السفر بنجاح', 'data' => $travelTypes]);
        } catch (\Exception $e) {
            Log::error('TravelTypeController::index failed', [
                'error' => $e->getMessage(),
                'message' => 'فشل في الحصول على أنواع السفر',
                'user' => $user->full_name ?? 'unknown'
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/travel-types",
     *     summary="Create a new travel type",
     *     tags={"Travel Type"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateTravelTypeRequest")
     *     ),
     *     @OA\Response(response=201, description="Travel type created successfully")
     * )
     */
    public function storeTravelType(CreateTravelTypeRequest $request)
    {
        try {
            $user = Auth::user();
            $hasPermission = $this->permissionService->checkPermission($user, 'travel_type2');
            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإنشاء أنواع السفر'
                ], 403);
            }

            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $dto = CreateTravelTypeDTO::fromRequest($request, $effectiveCompanyId);
            $travelType = $this->travelTypeService->createTravelType($dto);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء نوع السفر بنجاح',
                'data' => $travelType
            ], 201);
        } catch (\Exception $e) {
            Log::error('TravelTypeController::store failed', [
                'error' => $e->getMessage(),
                'message' => 'فشل في إنشاء نوع السفر',
                'created_by' => $user->full_name ?? 'unknown'
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء نوع السفر',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/travel-types/{id}",
     *     summary="Get travel type details",
     *     tags={"Travel Type"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function showTravelType(int $id, Request $request)
    {
        try {
            $user = Auth::user();
            $hasPermission = $this->permissionService->checkPermission($user, 'travel_type1');
            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مسموح بعرض تفاصيل نوع السفر'
                ], 403);
            }
            $travelType = $this->travelTypeService->getTravelType($id, $user);
            return response()->json(['success' => true, 'data' => $travelType]);
        } catch (\Exception $e) {
            Log::error('TravelTypeController::show failed', [
                'error' => $e->getMessage(),
                'message' => 'فشل في الحصول على نوع السفر',
                'user' => $user->full_name ?? 'unknown'
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/travel-types/{id}",
     *     summary="Update travel type",
     *     tags={"Travel Type"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateTravelTypeRequest")
     *     ),
     *     @OA\Response(response=200, description="Travel type updated successfully")
     * )
     */
    public function updateTravelType(UpdateTravelTypeRequest $request, $id)
    {
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->permissionService->checkPermission($user, 'travel_type3');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بتعديل أنواع السفر'
                ], 403);
            }
            $dto = UpdateTravelTypeDTO::fromRequest($request);
            $travelType = $this->travelTypeService->updateTravelType($id, $dto, Auth::user());
            return response()->json(['success' => true, 'message' => 'تم تحديث نوع السفر بنجاح', 'data' => $travelType]);
        } catch (\Exception $e) {
            Log::error('TravelTypeController::update failed', [
                'error' => $e->getMessage(),
                'message' => 'فشل في تحديث نوع السفر',
                'updated_by' => $user->full_name ?? 'unknown'
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/travel-types/{id}",
     *     summary="Delete travel type",
     *     tags={"Travel Type"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Travel type deleted successfully")
     * )
     */
    public function destroyTravelType($id)
    {
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->permissionService->checkPermission($user, 'travel_type4');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بحذف أنواع السفر'
                ], 403);
            }
            $this->travelTypeService->deleteTravelType($id, Auth::user());
            return response()->json(['success' => true, 'message' => 'تم حذف نوع السفر بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/travel-types/search",
     *     summary="Search travel types",
     *     tags={"Travel Type"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="query", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function search(Request $request)
    {
        try {
            $user = Auth::user();
            $hasPermission = $this->permissionService->checkPermission($user, 'travel_type1');
            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مسموح بالبحث في أنواع السفر'
                ], 403);
            }

            $query = $request->input('query', '');
            $travelTypes = $this->travelTypeService->searchTravelTypes($user, $query);

            return response()->json([
                'success' => true,
                'message' => 'تم البحث بنجاح',
                'data' => $travelTypes
            ]);
        } catch (\Exception $e) {
            Log::error('TravelTypeController::search failed', [
                'error' => $e->getMessage(),
                'user' => $user->full_name ?? 'unknown'
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
