<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ResidenceRenewal\CreateResidenceRenewalDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResidenceRenewal\StoreResidenceRenewalRequest;
use App\Services\ResidenceRenewalService;
use App\Services\SimplePermissionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Residence Renewal Costs",
 *     description="إدارة تكاليف تجديد الإقامة للموظفين"
 * )
 */
class ResidenceRenewalController extends Controller
{
    use ApiResponseTrait;

    protected $renewalService;
    protected $permissionService;

    public function __construct(
        ResidenceRenewalService $renewalService,
        SimplePermissionService $permissionService
    ) {
        $this->renewalService = $renewalService;
        $this->permissionService = $permissionService;
    }

    /**
     * @OA\Get(
     *     path="/api/residence-renewals",
     *     summary="عرض قائمة تكاليف تجديد الإقامة",
     *     tags={"Residence Renewal Costs"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب البيانات بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $filters = $request->only(['employee_id', 'search']);
            $perPage = (int) $request->input('per_page', 15);

            $renewals = $this->renewalService->getRenewals($effectiveCompanyId, $filters, $perPage);
            Log::info('Renewals fetched successfully', [
                'user_id' => Auth::id(),
                'company_id' => $effectiveCompanyId,
                'renewals_count' => $renewals->total(),
            ]);
            return $this->successResponse($renewals, 'تم جلب البيانات بنجاح', 200);
        } catch (\Exception $e) {
            Log::error('Error fetching renewals: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'company_id' => $effectiveCompanyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('حدث خطأ أثناء جلب البيانات', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/residence-renewals",
     *     summary="إضافة عملية تجديد إقامة جديدة وحساب التكاليف",
     *     tags={"Residence Renewal Costs"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "work_permit_fee", "residence_renewal_fees", "penalty_amount", "current_residence_expiry_date"},
     *             @OA\Property(property="employee_id", type="integer", example=767),
     *             @OA\Property(property="work_permit_fee", type="number", format="float", example=2000.00),
     *             @OA\Property(property="residence_renewal_fees", type="number", format="float", example=500.00),
     *             @OA\Property(property="penalty_amount", type="number", format="float", example=200.00),
     *             @OA\Property(property="current_residence_expiry_date", type="string", format="date", example="2026-03-14"),
     *             @OA\Property(property="is_manual_shares", type="boolean", example=true),
     *             @OA\Property(property="employee_share", type="number", format="float", example=600.00),
     *             @OA\Property(property="company_share", type="number", format="float", example=1200.00),
     *             @OA\Property(property="notes", type="string", example="ملاحظات إضافية")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201, 
     *         description="تم إضافة السجل وحساب التكاليف بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم إضافة السجل وحساب التكاليف بنجاح"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="renewal_cost_id", type="integer", example=13),
     *                 @OA\Property(property="total_amount", type="string", example="2700.00"),
     *                 @OA\Property(property="employee_share", type="string", example="600.00"),
     *                 @OA\Property(property="company_share", type="string", example="500.00"),
     *                 @OA\Property(property="grand_total", type="string", example="2700.00")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function store(StoreResidenceRenewalRequest $request): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $dto = CreateResidenceRenewalDTO::fromRequest($request->validated(), $effectiveCompanyId, Auth::id());

            $renewal = $this->renewalService->createRenewal($dto);
            Log::info('Renewal created successfully', [
                'user_id' => Auth::id(),
                'company_id' => $effectiveCompanyId,
                'renewal_data' => $renewal,
            ]);
            return $this->successResponse($renewal, 'تم إضافة السجل وحساب التكاليف بنجاح', 201);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            $message = $e->getMessage() ?: 'حدث خطأ أثناء إضافة السجل';
            Log::error('Error creating renewal: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString(),
                'error' => $e->getMessage(),
            ]);

            if ($code >= 400 && $code < 500) {
                return $this->errorResponse($message, $code);
            }

            Log::error('Error creating renewal: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('حدث خطأ أثناء إضافة السجل', 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/residence-renewals/{id}",
     *     summary="عرض تفاصيل تجديد الإقامة",
     *     tags={"Residence Renewal Costs"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب التفاصيل بنجاح"),
     *     @OA\Response(response=404, description="السجل غير موجود"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $renewal = $this->renewalService->getRenewal($id, $effectiveCompanyId);

            if (!$renewal) {
                Log::error('Renewal not found', [
                    'user_id' => Auth::id(),
                    'company_id' => $effectiveCompanyId,
                    'renewal_id' => $id,
                ]);
                return $this->errorResponse('السجل غير موجود', 404);
            }

            return $this->successResponse($renewal, 'تم جلب التفاصيل بنجاح', 200);
        } catch (\Exception $e) {
            Log::error('Error showing renewal: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'company_id' => $effectiveCompanyId,
                'renewal_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('حدث خطأ أثناء جلب التفاصيل', 500);
        }
    }


    /**
     * @OA\Delete(
     *     path="/api/residence-renewals/{id}",
     *     summary="حذف سجل تجديد الإقامة",
     *     tags={"Residence Renewal Costs"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم الحذف بنجاح"),
     *     @OA\Response(response=404, description="السجل غير موجود"),
     *     @OA\Response(response=400, description="خطأ في الطلب"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $this->renewalService->deleteRenewal($id, $effectiveCompanyId);
            Log::info('Renewal deleted successfully', [
                'user_id' => Auth::id(),
                'company_id' => $effectiveCompanyId,
                'renewal_id' => $id,
            ]);
            return $this->successResponse(null, 'تم الحذف بنجاح', 200);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            $message = $e->getMessage() ?: 'حدث خطأ أثناء الحذف';

            if ($code >= 400 && $code < 500) {
                return $this->errorResponse($message, $code);
            }

            Log::error('Error deleting renewal: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'renewal_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('حدث خطأ أثناء الحذف', 500);
        }
    }
}
