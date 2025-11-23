<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Travel\CreateTravelDTO;
use App\DTOs\Travel\UpdateTravelDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\CreateTravelRequest;
use App\Http\Requests\Travel\UpdateTravelRequest;
use App\Http\Requests\Travel\UpdateTravelStatusRequest;
use App\Services\SimplePermissionService;
use App\Services\TravelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TravelController extends Controller
{
    protected $travelService;
    protected $permissionService;

    public function __construct(TravelService $travelService, SimplePermissionService $permissionService)
    {
        $this->travelService = $travelService;
        $this->permissionService = $permissionService;
    }

    /**
     * @OA\Get(
     *     path="/api/travels",
     *     summary="Get list of travel requests",
     *     tags={"Travel"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index()
    {
        $travels = $this->travelService->getTravels(Auth::user());
        return response()->json(['success' => true, 'data' => $travels]);
    }

    /**
     * @OA\Post(
     *     path="/api/travels",
     *     summary="Create a new travel request",
     *     tags={"Travel"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateTravelRequest")
     *     ),
     *     @OA\Response(response=201, description="Travel request created successfully")
     * )
     */
    public function store(CreateTravelRequest $request)
    {
        try {
            $user = Auth::user();
            // Permission check (assumes permission key 'travel3')
            $hasPermission = $this->permissionService->checkPermission($user, 'travel3');
            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإنشاء طلبات السفر'
                ], 403);
            }

            // Get effective company ID from the permission service (same as before)
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            // Determine employee_id: if company/admin, can specify employee_id, otherwise use own id
            $employeeId = $user->user_id;
            if (($user->user_type === 'company' || $user->user_type === 'admin') && $request->has('employee_id')) {
                $employeeId = $request->input('employee_id');
            }

            $dto = CreateTravelDTO::fromRequest($request, $employeeId, $effectiveCompanyId, $user->user_id);
            $travel = $this->travelService->createTravel($dto);

            Log::info('TravelController::store', [
                'success' => true,
                'message' => 'تم إنشاء طلب السفر بنجاح',
                'created_by' => $user->full_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء طلب السفر بنجاح',
                'data' => $travel
            ], 201);
        } catch (\Exception $e) {
            Log::error('TravelController::store failed', [
                'error' => $e->getMessage(),
                'message' => 'فشل في إنشاء طلب السفر',
                'created_by' => $user->full_name ?? 'unknown'
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء طلب السفر',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/travels/{id}",
     *     summary="Get travel request details",
     *     tags={"Travel"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function show($id)
    {
        try {
            $user = Auth::user();
            $travel = $this->travelService->getTravel($id, $user);
            Log::info('TravelController::show', [
                'success' => true,
                'message' => 'تم الحصول على طلب السفر بنجاح',
                'created_by' => $user->full_name
            ]);
            return response()->json(['success' => true, 'data' => $travel]);
        } catch (\Exception $e) {
            Log::error('TravelController::show failed', [
                'error' => $e->getMessage(),
                'message' => 'فشل في الحصول على طلب السفر',
                'created_by' => $user->full_name ?? 'unknown'
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/travels/{id}",
     *     summary="Update travel request",
     *     tags={"Travel"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateTravelRequest")
     *     ),
     *     @OA\Response(response=200, description="Travel request updated successfully")
     * )
     */
    public function update(UpdateTravelRequest $request, $id)
    {
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->permissionService->checkPermission($user, 'travel4');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بتعديل طلبات السفر'
                ], 403);
            }
            $dto = UpdateTravelDTO::fromRequest($request);
            Log::info('TravelController::getUpdateDTOData', [
                'success' => true,
                'dto' => $dto,
                'created by' => $user->full_name
            ]);
            $travel = $this->travelService->updateTravel($id, $dto, Auth::user());
            Log::info('TravelController::update', [
                'success' => true,
                'message' => 'تم تحديث طلب السفر بنجاح',
                'created_by' => $user->full_name
            ]);
            return response()->json(['success' => true, 'message' => 'تم تحديث طلب السفر بنجاح', 'data' => $travel]);
        } catch (\Exception $e) {
            Log::error('TravelController::update failed', [
                'error' => $e->getMessage(),
                'message' => 'فشل في تحديث طلب السفر',
                'created_by' => $user->full_name ?? 'unknown'
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/travels/{id}",
     *     summary="Delete travel request",
     *     tags={"Travel"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Travel request deleted successfully")
     * )
     */
    public function cancel($id)
    {
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->permissionService->checkPermission($user, 'travel4');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإلغاء طلبات السفر'
                ], 403);
            }
            $this->travelService->cancelTravel($id, Auth::user());
            return response()->json(['success' => true, 'message' => 'تم إلغاء طلب السفر بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/travels/{id}/approve-or-reject",
     *     summary="Approve or Reject travel request",
     *     tags={"Travel"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="action", type="string", enum={"approve", "reject"}, description="Action to perform on the travel request"),
     *         )
     *     ),
     *     @OA\Response(response=200, description="Travel request status updated successfully")
     * )
     */


    public function approveTravel(UpdateTravelStatusRequest $request, int $id)
    {
        $user = Auth::user();
        try {
            $isUserHasThisPermission = $this->permissionService->checkPermission($user, 'travel4');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بمراجعة طلبات السفر'
                ], 403);
            }

            $action = $request->input('action'); // approve or reject

            Log::info('TravelController::Request received', [
                'request' => $request->all(),
                'action' => $action,
                'created by' => $user->full_name
            ]);

            if ($action === 'approve') {
                // استدعاء خدمة الموافقة على الطلب
                $application = $this->travelService->approveTravel($id, $request, $user);

                Log::info('TravelController::Approved', [
                    'success' => true,
                    'action' => $action,
                    'message' => 'تم الموافقة على طلب السفر بنجاح',
                    'created by' => $user->full_name
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'تم الموافقة على طلب السفر بنجاح',
                    'data' => $application
                ]);
            } else {

                $application = $this->travelService->rejectTravel($id, $request, $user);

                if (!$application) {
                    return response()->json([
                        'success' => false,
                        'message' => 'طلب السفر غير موجود'
                    ], 404);
                }

                Log::info('TravelController::Rejected', [
                    'success' => true,
                    'action' => $action,
                    'message' => 'تم رفض طلب السفر بنجاح',
                    'application' => $application,
                    'created by' => $user->full_name
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'تم رفض طلب السفر بنجاح',
                    'data' => $application
                ]);
            }
        } catch (\Exception $e) {
            Log::error('TravelController::approveApplication failed', [
                'message' => 'فشل في مراجعة طلب السفر',
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ]);
            return response()->json([
                'success' => false,
                'message' => 'فشل في مراجعة طلب السفر',
                'error' => $e->getMessage(),
                'created by' => $user->full_name
            ], 500);
        }
    }
}
