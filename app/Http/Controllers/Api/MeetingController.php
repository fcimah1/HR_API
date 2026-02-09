<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MeetingService;
use App\Services\SimplePermissionService;
use App\Http\Requests\Meeting\CreateMeetingRequest;
use App\Http\Requests\Meeting\UpdateMeetingRequest;
use App\DTOs\Meeting\CreateMeetingDTO;
use App\DTOs\Meeting\UpdateMeetingDTO;
use App\DTOs\Meeting\MeetingFilterDTO;
use App\Http\Resources\MeetingResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(name="Meetings", description="إدارة الاجتماعات")
 */
class MeetingController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly MeetingService $meetingService,
        private readonly SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/meetings",
     *     summary="عرض قائمة الاجتماعات",
     *     tags={"Meetings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="date", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="paginate", in="query", @OA\Schema(type="boolean", default="true")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default="10")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default="1")),
     *     @OA\Response(response=200, description="تم جلب الاجتماعات بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $filters = MeetingFilterDTO::fromRequest($request->all());
            $meetings = $this->meetingService->getMeetings($filters, $companyId);

            return $filters->paginate
                ? $this->successResponse(MeetingResource::collection($meetings)->response()->getData(true), 'تم جلب الاجتماعات بنجاح')
                : $this->successResponse(MeetingResource::collection($meetings), 'تم جلب الاجتماعات بنجاح');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/meetings",
     *     summary="إضافة اجتماع جديد",
     *     tags={"Meetings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CreateMeetingRequest")),
     *     @OA\Response(response=201, description="تم إنشاء الاجتماع بنجاح"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function store(CreateMeetingRequest $request): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $dto = CreateMeetingDTO::fromRequest($request->validated(), $companyId);
            $meeting = $this->meetingService->createMeeting($dto);

            return $this->successResponse(new MeetingResource($meeting), 'تم إنشاء الاجتماع بنجاح', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/meetings/{id}",
     *     summary="عرض تفاصيل اجتماع",
     *     tags={"Meetings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب تفاصيل الاجتماع"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $meeting = $this->meetingService->getMeeting($id, $companyId);

            return $this->successResponse(new MeetingResource($meeting), 'تم جلب تفاصيل الاجتماع بنجاح');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/meetings/{id}",
     *     summary="تحديث اجتماع",
     *     tags={"Meetings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/UpdateMeetingRequest")),
     *     @OA\Response(response=200, description="تم تحديث الاجتماع بنجاح"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function update(UpdateMeetingRequest $request, int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $dto = UpdateMeetingDTO::fromRequest($request->validated());
            $meeting = $this->meetingService->updateMeeting($id, $companyId, $dto);

            return $this->successResponse(new MeetingResource($meeting), 'تم تحديث الاجتماع بنجاح');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/meetings/{id}",
     *     summary="حذف اجتماع",
     *     tags={"Meetings"},   
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم حذف الاجتماع بنجاح"),
     *     @OA\Response(response=404, description="غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $this->meetingService->deleteMeeting($id, $companyId);

            return $this->successResponse(null, 'تم حذف الاجتماع بنجاح');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
