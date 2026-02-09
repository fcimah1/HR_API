<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Termination\CreateTerminationDTO;
use App\DTOs\Termination\UpdateTerminationDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Termination\StoreTerminationRequest;
use App\Http\Requests\Termination\UpdateTerminationRequest;
use App\Services\SimplePermissionService;
use App\Services\TerminationService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Termination Management",
 *     description="إدارة إنهاء الخدمة"
 * )
 */
class TerminationController extends Controller
{
    use ApiResponseTrait;

    protected $terminationService;
    protected $permissionService;

    public function __construct(
        TerminationService $terminationService,
        SimplePermissionService $permissionService
    ) {
        $this->terminationService = $terminationService;
        $this->permissionService = $permissionService;
    }

    /**
     * @OA\Get(
     *     path="/api/terminations",
     *     summary="عرض قائمة طلبات إنهاء الخدمة",
     *     tags={"Termination Management"},
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

            $terminations = $this->terminationService->getTerminations($effectiveCompanyId, $filters, $perPage);

            if (!$terminations) {
                Log::info('TerminationController::index - No terminations found', [
                    'user_id' => Auth::user()->user_id,
                ]);
                return $this->errorResponse('لا توجد بيانات', 404);
            }
            Log::info('TerminationController::index - Terminations fetched successfully', [
                'user_id' => Auth::user()->user_id,
                'terminations' => $terminations,
            ]);
            return $this->successResponse($terminations, 'تم جلب البيانات بنجاح', 200);
        } catch (\Exception $e) {
            Log::error('Error fetching terminations: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('حدث خطأ أثناء جلب البيانات', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/terminations",
     *     summary="إضافة طلب إنهاء خدمة جديد",
     *     tags={"Termination Management"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"employee_id", "notice_date", "termination_date", "reason"},
     *                 @OA\Property(property="employee_id", type="integer", example=767),
     *                 @OA\Property(property="notice_date", type="string", format="date", example="2026-01-01"),
     *                 @OA\Property(property="termination_date", type="string", format="date", example="2026-01-18"),
     *                 @OA\Property(property="reason", type="string", example="Reason for termination"),
     *                 @OA\Property(property="document_file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="تم إضافة الطلب بنجاح"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=401, description="غير مصرح"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function store(StoreTerminationRequest $request): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $dto = CreateTerminationDTO::fromRequest($request->validated(), $effectiveCompanyId, Auth::id());

            $termination = $this->terminationService->createTermination($dto);

            if (!$termination) {
                Log::info('TerminationController::store - No termination created', [
                    'user_id' => Auth::user()->user_id,
                ]);
                return $this->errorResponse('لا يمكن إضافة الطلب', 404);
            }
            Log::info('TerminationController::store - Termination created successfully', [
                'user_id' => Auth::user()->user_id,
                'termination' => $termination,
            ]);
            return $this->successResponse($termination, 'تم إضافة الطلب بنجاح', 201);
        } catch (\Exception $e) {
            Log::error('Error creating termination: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::user()->user_id,
            ]);
            return $this->errorResponse('حدث خطأ أثناء إضافة الطلب', 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/terminations/{id}",
     *     summary="عرض تفاصيل الطلب",
     *     tags={"Termination Management"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب التفاصيل بنجاح"),
     *     @OA\Response(response=404, description="الطلب غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $termination = $this->terminationService->getTermination($id, $effectiveCompanyId);

            if (!$termination) {
                Log::info('TerminationController::show - No termination found', [
                    'user_id' => Auth::user()->user_id,
                ]);
                return $this->errorResponse('الطلب غير موجود', 404);
            }

            Log::info('TerminationController::show - Termination fetched successfully', [
                'user_id' => Auth::user()->user_id,
                'termination' => $termination,
            ]);
            return $this->successResponse($termination, 'تم جلب التفاصيل بنجاح', 200);
        } catch (\Exception $e) {
            Log::error('Error showing termination: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'termination_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::user()->user_id,
            ]);
            return $this->errorResponse('حدث خطأ أثناء جلب التفاصيل', 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/terminations/{id}",
     *     summary="تعديل طلب إنهاء خدمة",
     *     tags={"Termination Management"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="notice_date", type="string", format="date", example="2026-01-01"),
     *             @OA\Property(property="termination_date", type="string", format="date", example="2026-01-18"),
     *             @OA\Property(property="reason", type="string", example="Updated reason"),
     *             @OA\Property(property="status", type="string", description="Pending, Approved, Rejected", example="Pending")
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم تعديل الطلب بنجاح"),
     *     @OA\Response(response=404, description="الطلب غير موجود"),
     *     @OA\Response(response=400, description="لا يمكن التعديل"),
     *     @OA\Response(response=401, description="غير مصرح"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function update(UpdateTerminationRequest $request, int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $dto = UpdateTerminationDTO::fromRequest($request->validated());

            $termination = $this->terminationService->updateTermination($id, $dto, $effectiveCompanyId);

            if (!$termination) {
                Log::info('TerminationController::update - No termination found', [
                    'user_id' => Auth::user()->user_id,
                ]);
                return $this->errorResponse('الطلب غير موجود', 404);
            }

            Log::info('TerminationController::update - Termination updated successfully', [
                'user_id' => Auth::user()->user_id,
                'termination' => $termination,
            ]);
            return $this->successResponse($termination, 'تم تعديل الطلب بنجاح', 200);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            $message = $e->getMessage() ?: 'حدث خطأ أثناء تعديل الطلب';

            if ($code >= 400 && $code < 500) {
                return $this->errorResponse($message, $code);
            }

            Log::error('Error updating termination: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'termination_id' => $id,
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::user()->user_id,
            ]);
            return $this->errorResponse('حدث خطأ أثناء تعديل الطلب', 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/terminations/{id}",
     *     summary="حذف طلب إنهاء خدمة",
     *     tags={"Termination Management"},
     *     security={{ "bearerAuth": {} }},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم الحذف بنجاح"),
     *     @OA\Response(response=404, description="الطلب غير موجود"),
     *     @OA\Response(response=400, description="لا يمكن الحذف"),
     *     @OA\Response(response=401, description="غير مصرح"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $this->terminationService->deleteTermination($id, $effectiveCompanyId);

            Log::info('TerminationController::destroy - Termination deleted successfully', [
                'user_id' => Auth::user()->user_id,
                'termination_id' => $id,
            ]);
            return $this->successResponse(null, 'تم الحذف بنجاح', 200);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            $message = $e->getMessage() ?: 'حدث خطأ أثناء الحذف';

            if ($code >= 400 && $code < 500) {
                return $this->errorResponse($message, $code);
            }

            Log::error('Error deleting termination: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'termination_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('حدث خطأ أثناء الحذف', 500);
        }
    }
}
