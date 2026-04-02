<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EventService;
use App\Http\Requests\Event\CreateEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\DTOs\Event\CreateEventDTO;
use App\DTOs\Event\UpdateEventDTO;
use App\DTOs\Event\EventFilterDTO;
use App\Http\Resources\EventResource;
use App\Services\SimplePermissionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(name="Events", description="إدارة الأحداث")
 */
class EventController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected EventService $service,
        protected SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/events",
     *     summary="عرض قائمة الأحداث مع الفلترة والبحث",
     *     tags={"Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="البحث في العنوان أو الملاحظات"),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer"), description="عدد العناصر في الصفحة"),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Response(response=200, description="تم جلب الأحداث بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $filters = EventFilterDTO::fromRequest($request, $companyId, $user);
            $result = $this->service->getPaginatedEvents($filters);

            Log::info('EventController::index success', [
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'filters' => $filters,
                'result' => $result
            ]);
            return $this->successResponse([
                'events' => EventResource::collection($result['data']),
                'pagination' => [
                    'total' => $result['total'],
                    'per_page' => $result['per_page'],
                    'current_page' => $result['current_page'],
                    'last_page' => $result['last_page'],
                ]
            ], 'تم جلب الأحداث بنجاح');
        } catch (\Exception $e) {
            Log::error('EventController::index failed', [
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('حدث خطأ أثناء جلب الأحداث', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/events",
     *     summary="إضافة حدث جديد",
     *     tags={"Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"event_title", "event_date", "event_time"},
     *             @OA\Property(property="event_title", type="string", example="ترقية وتكريم"),
     *             @OA\Property(property="event_date", type="string", format="date", example="2026-02-24"),
     *             @OA\Property(property="event_time", type="string", example="09:00 AM"),
     *             @OA\Property(property="employee_ids", type="array", @OA\Items(type="integer"), example={702, 726}),
     *             @OA\Property(property="event_note", type="string", example="ملاحظات الحدث"),
     *             @OA\Property(property="event_color", type="string", example="#7267EF"),
     *             @OA\Property(property="is_show_calendar", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=201, description="تم إضافة الحدث بنجاح"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function store(CreateEventRequest $request)
    {
        try {
            $companyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $dto = CreateEventDTO::fromRequest($request, $companyId);
            $event = $this->service->createEvent($dto);

            Log::info('EventController::store success', [
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'dto' => $dto,
                'event' => $event
            ]);
            return $this->successResponse(new EventResource($event), 'تم إضافة الحدث بنجاح', 201);
        } catch (\Exception $e) {
            Log::error('EventController::store failed', [
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('حدث خطأ أثناء إضافة الحدث', 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/events/{id}",
     *     summary="عرض تفاصيل حدث محدد",
     *     tags={"Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم جلب تفاصيل الحدث"),
     *     @OA\Response(response=404, description="الحدث غير موجود"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function show($id)
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $event = $this->service->getEventById((int)$id, $companyId, $user);

            if (!$event) {
                Log::info('EventController::show not found', [
                    'company_id' => $companyId,
                    'user_id' => Auth::id(),
                    'id' => $id
                ]);
                return $this->errorResponse('الحدث غير موجود', 404);
            }

            Log::info('EventController::show success', [
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'id' => $id,
                'event' => $event
            ]);
            return $this->successResponse(new EventResource($event), 'تم جلب تفاصيل الحدث');
        } catch (\Exception $e) {
            Log::error('EventController::show failed', [
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $status = in_array($e->getCode(), [403, 404]) ? $e->getCode() : 500;
            return $this->errorResponse($e->getMessage(), (int)$status);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/events/{id}",
     *     summary="تحديث بيانات حدث",
     *     tags={"Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="event_title", type="string"),
     *             @OA\Property(property="event_date", type="string", format="date"),
     *             @OA\Property(property="event_time", type="string"),
     *             @OA\Property(property="event_note", type="string"),
     *             @OA\Property(property="event_color", type="string"),
     *             @OA\Property(property="is_show_calendar", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم تحديث الحدث بنجاح"),
     *     @OA\Response(response=404, description="الحدث غير موجود"),
     *     @OA\Response(response=422, description="فشل التحقق من البيانات"),
     *     @OA\Response(response=403, description="غير مصرح لك بتعديل هذا الحدث"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function update(UpdateEventRequest $request, $id)
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $dto = UpdateEventDTO::fromRequest($request);
            $event = $this->service->updateEvent((int)$id, $companyId, $dto, $user);

            Log::info('EventController::update success', [
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'id' => $id,
                'dto' => $dto,
                'event' => $event
            ]);
            return $this->successResponse(new EventResource($event), 'تم تحديث الحدث بنجاح');
        } catch (\Exception $e) {
            Log::error('EventController::update failed', [
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $status = in_array($e->getCode(), [403, 404]) ? $e->getCode() : 500;
            return $this->errorResponse($e->getMessage(), (int)$status);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/events/{id}",
     *     summary="حذف حدث",
     *     tags={"Events"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم حذف الحدث بنجاح"),
     *     @OA\Response(response=404, description="الحدث غير موجود"),
     *     @OA\Response(response=403, description="غير مصرح لك بحذف هذا الحدث"),
     *     @OA\Response(response=401, description="غير مصرح - يرجى تسجيل الدخول"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);
            $success = $this->service->deleteEvent((int)$id, $companyId, $user);

            Log::info('EventController::destroy success', [
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'id' => $id,
                'success' => $success
            ]);
            return $this->successResponse(null, 'تم حذف الحدث بنجاح');
        } catch (\Exception $e) {
            Log::error('EventController::destroy failed', [
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $status = in_array($e->getCode(), [403, 404]) ? $e->getCode() : 500;
            return $this->errorResponse($e->getMessage(), (int)$status);
        }
    }
}
