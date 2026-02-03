<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CustodyClearance\CustodyFilterDTO;
use App\DTOs\CustodyClearance\CustodyClearanceFilterDTO;
use App\DTOs\CustodyClearance\CreateCustodyClearanceDTO;
use App\DTOs\CustodyClearance\ApproveCustodyClearanceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustodyClearance\GetCustodiesRequest;
use App\Http\Requests\CustodyClearance\CreateCustodyClearanceRequest;
use App\Http\Requests\CustodyClearance\ApproveCustodyClearanceRequest;
use App\Services\CustodyClearanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Custody Clearance",
 *     description="إدارة إخلاء طرف العهد"
 * )
 */
class CustodyClearanceController extends Controller
{
    public function __construct(
        protected CustodyClearanceService $clearanceService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/custody-clearances/types",
     *     summary="عرض أنواع إخلاء الطرف",
     *     tags={"Custody Clearance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب الأنواع بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب أنواع إخلاء الطرف بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="value", type="string", example="resignation"),
     *                     @OA\Property(property="case_name", type="string", example="RESIGNATION"),
     *                     @OA\Property(property="label_en", type="string", example="Resignation"),
     *                     @OA\Property(property="label_ar", type="string", example="استقالة")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getClearanceTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'تم جلب أنواع إخلاء الطرف بنجاح',
            'data' => \App\Enums\CustodyClearanceTypeEnum::toArray(),
        ]);
    }



    // /**
    //  * @OA\Get(
    //  *     path="/api/assets",
    //  *     summary="عرض الأصول",
    //  *     tags={"Custody Clearance"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(name="employee_id", in="query", description="معرف الموظف", @OA\Schema(type="integer")),
    //  *     @OA\Parameter(name="search", in="query", description="بحث", @OA\Schema(type="string")),
    //  *     @OA\Parameter(name="status", in="query", description="حالة الأصل", @OA\Schema(type="string", enum={"working","damaged","disposed"})),
    //  *     @OA\Response(
    //  *         response=200,
    //  *         description="تم جلب العهد بنجاح",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="success", type="boolean", example=true),
    //  *             @OA\Property(property="message", type="string", example="تم جلب العهد بنجاح"),
    //  *             @OA\Property(
    //  *                 property="data",
    //  *                 type="array",
    //  *                 @OA\Items(
    //  *                     @OA\Property(property="assets_id", type="integer", example=1),
    //  *                     @OA\Property(property="name", type="string", example="لابتوب Dell"),
    //  *                     @OA\Property(property="serial_number", type="string", example="SN123456"),
    //  *                     @OA\Property(property="company_asset_code", type="string", example="AST-001"),
    //  *                     @OA\Property(property="status", type="string", example="working"),
    //  *                     @OA\Property(property="purchase_date", type="string", format="date", example="2024-01-01"),
    //  *                     @OA\Property(property="brand_name", type="string", example="Dell"),
    //  *                     @OA\Property(property="assets_category", type="string", example="Laptops"),
    //  *                     @OA\Property(
    //  *                         property="employee",
    //  *                         type="object",
    //  *                         @OA\Property(property="id", type="integer", example=101),
    //  *                         @OA\Property(property="name", type="string", example="أحمد محمد")
    //  *                     )
    //  *                 )
    //  *             ),
    //  *             @OA\Property(
    //  *                 property="pagination",
    //  *                 type="object",
    //  *                 @OA\Property(property="current_page", type="integer", example=1),
    //  *                 @OA\Property(property="last_page", type="integer", example=5),
    //  *                 @OA\Property(property="per_page", type="integer", example=15),
    //  *                 @OA\Property(property="total", type="integer", example=75)
    //  *             )
    //  *         )
    //  *     ),
    //  *     @OA\Response(response=403, description="غير مصرح"),
    //  *     @OA\Response(response=500, description="خطأ في الخادم")
    //  * )
    //  */
    // public function getAssets(GetCustodiesRequest $request): JsonResponse
    // {
    //     try {
    //         $user = Auth::user();
    //         $filters = CustodyFilterDTO::fromRequest($request->validated());

    //         $result = $this->clearanceService->getCustodiesForEmployee($user, $filters);

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'تم جلب العهد بنجاح',
    //             'data' => $result['data'],
    //             'pagination' => $result['pagination'],
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('CustodyClearanceController::getCustodies failed', [
    //             'error' => $e->getMessage(),
    //         ]);

    //         $statusCode = str_contains($e->getMessage(), 'صلاحية') ? 403 : 500;

    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage(),
    //         ], $statusCode);
    //     }
    // }

    /**
     * @OA\Get(
     *     path="/api/custody-clearances",
     *     summary="عرض طلبات إخلاء الطرف",
     *     tags={"Custody Clearance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"pending","approved","rejected"})),
     *     @OA\Parameter(name="clearance_type", in="query", @OA\Schema(type="string", enum={"resignation","termination","transfer","other"})),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب الطلبات بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب طلبات إخلاء الطرف بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="clearance_id", type="integer", example=10),
     *                     @OA\Property(property="employee_id", type="integer", example=101),
     *                     @OA\Property(property="clearance_date", type="string", format="date", example="2026-01-15"),
     *                     @OA\Property(property="clearance_type", type="string", example="resignation"),
     *                     @OA\Property(property="status", type="string", example="pending"),
     *                     @OA\Property(property="notes", type="string", example="إخلاء طرف بسبب الاستقالة"),
     *                     @OA\Property(
     *                         property="employee",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=101),
     *                         @OA\Property(property="name", type="string", example="أحمد محمد")
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-01 10:00:00")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=5)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $filters = CustodyClearanceFilterDTO::fromRequest($request->all());

            $result = $this->clearanceService->getPaginatedClearances($filters, $user);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب طلبات إخلاء الطرف بنجاح',
                'data' => $result['data'],
                'pagination' => $result['pagination'],
            ]);
        } catch (\Exception $e) {
            Log::error('CustodyClearanceController::index failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/custody-clearances/{id}",
     *     summary="عرض تفاصيل طلب إخلاء طرف",
     *     tags={"Custody Clearance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب الطلب بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب تفاصيل الطلب بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="clearance_id", type="integer", example=10),
     *                 @OA\Property(property="employee_id", type="integer", example=101),
     *                 @OA\Property(property="clearance_date", type="string", format="date", example="2026-01-15"),
     *                 @OA\Property(property="clearance_type", type="string", example="resignation"),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="notes", type="string", example="ملاحظات"),
     *                 @OA\Property(
     *                     property="items",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="item_id", type="integer", example=1),
     *                         @OA\Property(property="asset_id", type="integer", example=5),
     *                         @OA\Property(
     *                             property="asset",
     *                             type="object",
     *                             @OA\Property(property="name", type="string", example="Laptop"),
     *                             @OA\Property(property="code", type="string", example="AST-001")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="approvals",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="level", type="integer", example=1),
     *                         @OA\Property(property="status", type="string", example="approved"),
     *                         @OA\Property(property="approver_name", type="string", example="مدير القسم")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="الطلب غير موجود")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $result = $this->clearanceService->getClearanceById($id, $user);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب تفاصيل الطلب بنجاح',
                'data' => $result->toArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('CustodyClearanceController::show failed', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404 : (str_contains($e->getMessage(), 'صلاحية') ? 403 : 500);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/custody-clearances",
     *     summary="إنشاء طلب إخلاء طرف جديد",
     *     tags={"Custody Clearance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="employee_id", type="integer"),
     *             @OA\Property(property="clearance_date", type="string", format="date"),
     *             @OA\Property(property="clearance_type", type="string", enum={"resignation","termination","transfer","other"}),
     *             @OA\Property(property="asset_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="تم إنشاء الطلب بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إنشاء طلب إخلاء الطرف بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="clearance_id", type="integer", example=15),
     *                 @OA\Property(property="status", type="string", example="pending")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="خطأ في البيانات")
     * )
     */
    public function store(CreateCustodyClearanceRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $companyId = $user->user_type === 'company' ? $user->user_id : $user->company_id;

            $dto = CreateCustodyClearanceDTO::fromRequest(
                $request->validated(),
                $companyId,
                $user->user_id
            );

            $result = $this->clearanceService->createClearance($dto);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء طلب إخلاء الطرف بنجاح',
                'data' => $result->toArray(),
            ], 201);
        } catch (\Exception $e) {
            Log::error('CustodyClearanceController::store failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/custody-clearances/{id}/approve-or-reject",
     *     summary="الموافقة أو رفض طلب إخلاء الطرف",
     *     tags={"Custody Clearance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"action"},
     *             @OA\Property(property="action", type="string", enum={"approve","reject"}),
     *             @OA\Property(property="remarks", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تمت معالجة الطلب بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تمت الموافقة على طلب إخلاء الطرف بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="clearance_id", type="integer", example=10),
     *                 @OA\Property(property="status", type="string", example="approved"),
     *                 @OA\Property(property="approved_by", type="integer", example=202)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="خطأ في المعالجة"),
     *     @OA\Response(response=403, description="غير مصرح")
     * )
     */
    public function approveOrReject(int $id, ApproveCustodyClearanceRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $dto = ApproveCustodyClearanceDTO::fromRequest(
                $request->validated(),
                $user->user_id
            );

            $result = $this->clearanceService->approveOrRejectClearance($id, $dto);

            $message = $dto->action === 'approve'
                ? 'تمت الموافقة على طلب إخلاء الطرف بنجاح'
                : 'تم رفض طلب إخلاء الطرف';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result->toArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('CustodyClearanceController::approveOrReject failed', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            $statusCode = str_contains($e->getMessage(), 'صلاحية') || str_contains($e->getMessage(), 'يجب أن يوافق') ? 403 : 400;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
