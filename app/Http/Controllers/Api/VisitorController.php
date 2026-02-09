<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VisitorService;
use App\Services\SimplePermissionService;
use App\Http\Requests\Visitor\CreateVisitorRequest;
use App\Http\Requests\Visitor\UpdateVisitorRequest;
use App\DTOs\Visitor\CreateVisitorDTO;
use App\DTOs\Visitor\UpdateVisitorDTO;
use App\DTOs\Visitor\VisitorFilterDTO;
use App\Http\Resources\VisitorResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(name="Visitors", description="إدارة الزوار")
 */
class VisitorController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly VisitorService $visitorService,
        private readonly SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/visitors",
     *     summary="عرض قائمة الزوار",
     *     tags={"Visitors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="date", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="department_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="paginate", in="query", @OA\Schema(type="boolean", default="true")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default="10")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default="1")),
     *     @OA\Response(response=200, description="تم جلب الزوار بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $filters = VisitorFilterDTO::fromRequest($request->all());
            $visitors = $this->visitorService->getVisitors($filters, $companyId);

            return $filters->paginate
                ? $this->successResponse(VisitorResource::collection($visitors)->response()->getData(true), 'تم جلب الزوار بنجاح')
                : $this->successResponse(VisitorResource::collection($visitors), 'تم جلب الزوار بنجاح');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/visitors",
     *     summary="إضافة زائر جديد",
     *     tags={"Visitors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CreateVisitorRequest")),
     *     @OA\Response(response=201, description="تم إضافة الزائر بنجاح"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function store(CreateVisitorRequest $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $userId = Auth::user()->user_id;
            $dto = CreateVisitorDTO::fromRequest($request->validated(), $companyId, $userId);
            $visitor = $this->visitorService->createVisitor($dto);

            return $this->successResponse(new VisitorResource($visitor), 'تم إضافة الزائر بنجاح', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/visitors/{id}",
     *     summary="عرض تفاصيل زائر",
     *     tags={"Visitors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب تفاصيل الزائر"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $visitor = $this->visitorService->getVisitor($id, $companyId);

            return $this->successResponse(new VisitorResource($visitor), 'تم جلب تفاصيل الزائر بنجاح');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/visitors/{id}",
     *     summary="تحديث سجل زائر",
     *     tags={"Visitors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/UpdateVisitorRequest")),
     *     @OA\Response(response=200, description="تم تحديث السجل بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function update(UpdateVisitorRequest $request, int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $dto = UpdateVisitorDTO::fromRequest($request->validated());
            $visitor = $this->visitorService->updateVisitor($id, $companyId, $dto);

            return $this->successResponse(new VisitorResource($visitor), 'تم تحديث السجل بنجاح');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/visitors/{id}",
     *     summary="حذف سجل زائر",
     *     tags={"Visitors"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم حذف السجل بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $this->visitorService->deleteVisitor($id, $companyId);

            return $this->successResponse(null, 'تم حذف السجل بنجاح');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
