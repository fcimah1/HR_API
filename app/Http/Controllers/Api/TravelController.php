<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Travel\CreateTravelDTO;
use App\DTOs\Travel\UpdateTravelDTO;
use App\DTOs\Travel\TravelRequestFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Travel\CreateTravelRequest;
use App\Http\Requests\Travel\UpdateTravelRequest;
use App\Http\Requests\Travel\UpdateTravelStatusRequest;
use App\Http\Resources\TravelResource;
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
     *  @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (pending/approved/rejected)",
     *         @OA\Schema(type="string", enum={"pending", "approved", "rejected"})
     *     ),
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         description="Filter by employee ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Filter from date (Y-m-d)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="Filter to date (Y-m-d)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for filtering travel requests",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of travel requests",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TravelResource")),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب طلبات السفر بنجاح"),
     *             @OA\Property(property="created_by", type="string", example="أحمد علي"),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer", example=120),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=8),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=15)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بعرض طلبات السفر")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="حدث خطأ في الخادم")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $hasPermission = $this->permissionService->checkPermission($user, 'travel1');
            
            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مسموح بعرض طلبات السفر'
                ], 403);
            }

            $filters = TravelRequestFilterDTO::fromRequest($request->all());
            $result = $this->travelService->getTravels($user, $filters);

            // استخدام TravelResource::collection() لتحويل البيانات
            return TravelResource::collection($result['data'])->additional([
                'success' => true,
                'message' => 'تم جلب طلبات السفر بنجاح',
                'created_by' => $user->full_name ?? null,
                'pagination' => [
                    'total' => $result['total'],
                    'per_page' => $result['per_page'],
                    'current_page' => $result['current_page'],
                    'last_page' => $result['last_page'],
                    'from' => $result['from'],
                    'to' => $result['to'],
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('TravelController::index failed', [
                'error' => $e->getMessage(),
                'message' => 'فشل في الحصول على طلبات السفر',
                'created_by' => $user->full_name ?? 'unknown'
            ]);
            return response()->json([
                'success' => false,
                'created_by' => $user->full_name ?? 'unknown',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/travels",
     *     summary="Create a new travel request",
     *     tags={"Travel"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "start_date","end_date","visit_purpose","visit_place","travel_mode","arrangement_type","expected_budget","actual_budget"},
     *             @OA\Property(property="employee_id", type="integer", example=755, description="معرف الموظف (اختياري)"),
     *             @OA\Property(property="start_date", type="string", format="date", example="2026-01-01", description="تاريخ بداية السفر"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2026-01-03", description="تاريخ نهاية السفر"),
     *             @OA\Property(property="visit_purpose", type="string", example="سفر عمل لمؤتمر", description="غرض الزيارة"),
     *             @OA\Property(property="visit_place", type="string", example="الرياض", description="مكان الزيارة"),
     *             @OA\Property(property="travel_mode", type="integer", example=1, description="طريقة السفر (1-5)"),
     *             @OA\Property(property="arrangement_type", type="integer", example=335, description="نوع ترتيب السفر"),
     *             @OA\Property(property="expected_budget", type="number", format="float", example=1000.00, description="الميزانية المتوقعة"),
     *             @OA\Property(property="actual_budget", type="number", format="float", example=900.00, description="الميزانية الفعلية"),
     *             @OA\Property(property="description", type="string", example="وصف الرحلة", description="وصف (اختياري)"),
     *             @OA\Property(property="associated_goals", type="array", @OA\Items(type="string"), example={"هدف 1","هدف 2"}, description="الأهداف المرتبطة (اختياري)"),
     *             @OA\Property(property="remarks", type="string", example="ملاحظات إضافية", description="ملاحظات (اختياري)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Travel request created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="created_by", type="string"),
     *             @OA\Property(property="message", type="string", example="تم إنشاء طلب السفر بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - No permission",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بإنشاء طلبات السفر")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل في إنشاء طلب السفر")
     *         )
     *     )
     * )
     */
    public function storeTravel(CreateTravelRequest $request)
    {
        try {
            $user = Auth::user();
            // Permission check (assumes permission key 'travel3')
            $hasPermission = $this->permissionService->checkPermission($user, 'travel2');
            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'created_by' => $user->full_name ?? 'unknown',
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
            $travel = $this->travelService->createTravel($dto, $user);

            return response()->json([
                'success' => true,
                'created_by' => $user->full_name,
                'message' => 'تم إنشاء طلب السفر بنجاح',
                'data' => new TravelResource($travel)
            ], 201);
        } catch (\Exception $e) {
            Log::error('TravelController::store failed', [
                'error' => $e->getMessage(),
                'message' => 'فشل في إنشاء طلب السفر',
                'created_by' => $user->full_name ?? 'unknown'
            ]);
            return response()->json([
                'success' => false,
                'created_by' => $user->full_name ?? 'unknown',
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
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Travel request ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/TravelResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - No permission",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بعرض تفاصيل طلبات السفر")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Travel request not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="طلب السفر غير موجود")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل في الحصول على طلب السفر")
     *         )
     *     )
     * )
     */
    public function showTravel(int $id, Request $request)
    {
        try {
            $user = Auth::user();

            // التحقق من الصلاحيات
            $isUserHasThisPermission = $this->permissionService->checkPermission($user, 'travel2');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بعرض تفاصيل طلبات السفر'
                ], 403);
            }

            $travel = $this->travelService->getTravel($id, $user);
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
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Travel request ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-12-01"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-12-05"),
     *             @OA\Property(property="visit_purpose", type="string", example="سفر عمل معدل"),
     *             @OA\Property(property="visit_place", type="string", example="الرياض"),
     *             @OA\Property(property="travel_mode", type="integer", example=1),
     *             @OA\Property(property="arrangement_type", type="integer", example=1),
     *             @OA\Property(property="expected_budget", type="number", format="float", example=1000.00),
     *             @OA\Property(property="actual_budget", type="number", format="float", example=900.00),
     *             @OA\Property(property="description", type="string", example="وصف معدل"),
     *             @OA\Property(property="associated_goals", type="array", @OA\Items(type="string"), example={"هدف 1","هدف 2"}, description="الأهداف المرتبطة (اختياري)"),
     *             @OA\Property(property="remarks", type="string", example="ملاحظات معدلة")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Travel request updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث طلب السفر بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - No permission",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بتعديل طلبات السفر")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Travel request not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="طلب السفر غير موجود")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل في تحديث طلب السفر")
     *         )
     *     )
     * )
     */
    public function updateTravel(UpdateTravelRequest $request, $id)
    {
        try {
            $user = Auth::user();
            $isUserHasThisPermission = $this->permissionService->checkPermission($user, 'travel3');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بتعديل طلبات السفر'
                ], 403);
            }
            // Debug: Log the request before creating DTO
            Log::info('TravelController::updateTravel - Request data', [
                'request_all' => $request->all(),
                'request_json' => $request->json()->all(),
                'request_content' => $request->getContent()
            ]);
            
            // Try to parse JSON manually if Laravel fails
            $content = $request->getContent();
            
            // Fix common JSON syntax errors - add quotes to unquoted values in arrays
            $content = preg_replace('/\[\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*,\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\]/', '["$1", "$2"]', $content);
            $content = preg_replace('/\[\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\]/', '["$1"]', $content);
            
            $jsonData = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {

                throw new \Exception('Invalid JSON format - ' . json_last_error_msg()); 
            }
            
            
            // Create a new request with the fixed data
            $fixedRequest = new Request($jsonData);
            $dto = UpdateTravelDTO::fromRequest($fixedRequest);
            $travel = $this->travelService->updateTravel($id, $dto, Auth::user());
            return response()->json(['success' => true, 'message' => 'تم تحديث طلب السفر بنجاح', 'data' => new TravelResource($travel)]);
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
     *     summary="Cancel travel request",
     *     tags={"Travel"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Travel request ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Travel request cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إلغاء طلب السفر بنجاح")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - No permission",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بإلغاء طلبات السفر")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Travel request not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="طلب السفر غير موجود")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل في إلغاء طلب السفر")
     *         )
     *     )
     * )
     */
    public function cancelTravel($id)
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
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Travel request ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"action"},
     *             @OA\Property(property="action", type="string", enum={"approve", "reject"}, example="approve", description="Action to perform: approve or reject"),
     *             @OA\Property(property="remarks", type="string", example="موافق على السفر", description="Remarks for approval/rejection (optional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Travel request status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تحديث حالة طلب السفر بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - No permission",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بالموافقة على طلبات السفر")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Travel request not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="طلب السفر غير موجود")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل في تحديث حالة طلب السفر")
     *         )
     *     )
     * )
     */


    public function approveTravel(UpdateTravelStatusRequest $request, int $id)
    {
        $user = Auth::user();
        try {
            $isUserHasThisPermission = $this->permissionService->checkPermission($user, 'travel5');
            if (!$isUserHasThisPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بمراجعة طلبات السفر'
                ], 403);
            }

            $action = $request->input('action'); // approve or reject

            if ($action === 'approve') {
                // استدعاء خدمة الموافقة على الطلب
                $application = $this->travelService->approveTravel($id, $request, $user);

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

    /**
     * @OA\Get(
     *     path="/api/travels/enums",
     *     summary="Get travel enums",
     *     tags={"Travel"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Travel enums retrieved successfully")
     * )
     */
    public function getEnums()
    {
        try {
            $enums = $this->travelService->getTravelEnums();

            return response()->json([
                'success' => true,
                'message' => 'تم جلب قوائم السفر بنجاح',
                'data' => $enums
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب القوائم',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
