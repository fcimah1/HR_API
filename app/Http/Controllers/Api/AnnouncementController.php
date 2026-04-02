<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnnouncementService;
use App\Services\SimplePermissionService;
use App\Http\Requests\Announcement\CreateAnnouncementRequest;
use App\Http\Requests\Announcement\UpdateAnnouncementRequest;
use App\DTOs\Announcement\AnnouncementFilterDTO;
use App\DTOs\Announcement\CreateAnnouncementDTO;
use App\DTOs\Announcement\UpdateAnnouncementDTO;
use App\Http\Resources\AnnouncementResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Announcements",
 *     description="إدارة الإعلانات"
 * )
 */
class AnnouncementController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly AnnouncementService $announcementService,
        private readonly SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/announcements",
     *     security={{"bearerAuth": {}}},
     *     summary="عرض جميع الإعلانات",
     *     tags={"Announcements"},
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="paginate", in="query", @OA\Schema(type="boolean", default="true")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default="1")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default="10")),
     *     @OA\Response(response=200, description="تم جلب الإعلانات بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $filters = AnnouncementFilterDTO::fromRequest($request->all(), $effectiveCompanyId);
            $result = $this->announcementService->getAllAnnouncements($filters);

            if ($result instanceof \Illuminate\Pagination\LengthAwarePaginator) {
                $resource = AnnouncementResource::collection($result);
                return $this->paginatedResponse($resource, 'تم جلب الإعلانات بنجاح');
            }

            return $this->successResponse(AnnouncementResource::collection($result), 'تم جلب الإعلانات بنجاح');
        } catch (\Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء جلب الإعلانات', 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/announcements/{id}",
     *     summary="عرض إعلان معين",
     *     tags={"Announcements"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب الإعلان بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function show(int $id, Request $request): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $announcement = $this->announcementService->getAnnouncementById($id, $effectiveCompanyId);

            if (!$announcement) {
                return $this->errorResponse('الإعلان غير موجود', 404);
            }

            return $this->successResponse(new AnnouncementResource($announcement), 'تم جلب الإعلان بنجاح');
        } catch (\Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء جلب الإعلان', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/announcements",
     *     summary="إنشاء إعلان جديد",
     *     tags={"Announcements"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateAnnouncementRequest")
     *     ),
     *     @OA\Response(response=201, description="تم إنشاء الإعلان بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function store(CreateAnnouncementRequest $request): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $dto = CreateAnnouncementDTO::fromRequest(
                $request->validated(),
                $effectiveCompanyId,
                $request->user()->user_id
            );

            $announcement = $this->announcementService->createAnnouncement($dto);

            return $this->successResponse(new AnnouncementResource($announcement), 'تم إنشاء الإعلان بنجاح', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء إنشاء الإعلان', 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/announcements/{id}",
     *     summary="تحديث إعلان موجود",
     *     tags={"Announcements"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateAnnouncementRequest")
     *     ),
     *     @OA\Response(response=200, description="تم تحديث الإعلان بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function update(int $id, UpdateAnnouncementRequest $request): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $dto = UpdateAnnouncementDTO::fromRequest($request->validated());
            $announcement = $this->announcementService->updateAnnouncement($id, $effectiveCompanyId, $dto);

            if (!$announcement) {
                return $this->errorResponse('الإعلان غير موجود', 404);
            }

            return $this->successResponse(new AnnouncementResource($announcement), 'تم تحديث الإعلان بنجاح');
        } catch (\Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء تحديث الإعلان', 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/announcements/{id}",
     *     summary="حذف إعلان",
     *     tags={"Announcements"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم حذف الإعلان بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=500, description="خطأ في الخادم"),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $deleted = $this->announcementService->deleteAnnouncement($id, $effectiveCompanyId);

            if (!$deleted) {
                return $this->errorResponse('الإعلان غير موجود أو فشل الحذف', 404);
            }
            return $this->successResponse(null, 'تم حذف الإعلان بنجاح');
        } catch (\Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء حذف الإعلان', 500);
        }
    }
}
