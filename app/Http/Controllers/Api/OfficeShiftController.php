<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\DTOs\OfficeShift\OfficeShiftFilterDTO;
use App\DTOs\OfficeShift\CreateOfficeShiftDTO;
use App\DTOs\OfficeShift\UpdateOfficeShiftDTO;
use App\Http\Requests\OfficeShift\StoreOfficeShiftRequest;
use App\Http\Requests\OfficeShift\UpdateOfficeShiftRequest;
use App\Services\OfficeShiftService;
use App\Services\SimplePermissionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(name="OfficeShifts", description="إدارة نوبات العمل")
 */
class OfficeShiftController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected OfficeShiftService $officeShiftService,
        protected SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/office-shifts",
     *     summary="جلب قائمة نوبات العمل",
     *     tags={"OfficeShifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم بنجاح"),
     *     @OA\Response(response=422, description="بيانات غير صالحة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لجلب الموظفين التابعين"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $filters = OfficeShiftFilterDTO::fromArray($request->all());
        $shifts = $this->officeShiftService->getPaginatedShifts(Auth::user(), $filters);
        return $this->successResponse($shifts, 'تم جلب نوبات العمل بنجاح');
    }

    /**
     * @OA\Get(
     *     path="/api/office-shifts/{id}",
     *     summary="جلب تفاصيل نوبة عمل",
     *     tags={"OfficeShifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم بنجاح"),
     *     @OA\Response(response=422, description="بيانات غير صالحة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لجلب الموظفين التابعين"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $shift = $this->officeShiftService->getShiftDetails(Auth::user(), $id);
        if (!$shift) {
            return $this->errorResponse('نوبة العمل غير موجودة', 404);
        }
        return $this->successResponse($shift, 'تم جلب تفاصيل نوبة العمل بنجاح');
    }

    /**
     * @OA\Post(
     *     path="/api/office-shifts",
     *     summary="إضافة نوبة عمل جديدة",
     *     tags={"OfficeShifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="shift_name", type="string", example="Morning Shift"),
     *             @OA\Property(property="hours_per_day", type="integer", example=8),
     *             @OA\Property(property="monday_in_time", type="string", example="09:00"),
     *             @OA\Property(property="monday_out_time", type="string", example="17:00"),
     *             @OA\Property(property="monday_lunch_break", type="string", example="13:00"),
     *             @OA\Property(property="monday_lunch_break_out", type="string", example="14:00"),
     *             @OA\Property(property="tuesday_in_time", type="string", example="09:00"),
     *             @OA\Property(property="tuesday_out_time", type="string", example="17:00"),
     *             @OA\Property(property="tuesday_lunch_break", type="string", example="13:00"),
     *             @OA\Property(property="tuesday_lunch_break_out", type="string", example="14:00"),
     *             @OA\Property(property="wednesday_in_time", type="string", example="09:00"),
     *             @OA\Property(property="wednesday_out_time", type="string", example="17:00"),
     *             @OA\Property(property="wednesday_lunch_break", type="string", example="13:00"),
     *             @OA\Property(property="wednesday_lunch_break_out", type="string", example="14:00"),
     *             @OA\Property(property="thursday_in_time", type="string", example="09:00"),
     *             @OA\Property(property="thursday_out_time", type="string", example="17:00"),
     *             @OA\Property(property="thursday_lunch_break", type="string", example="13:00"),
     *             @OA\Property(property="thursday_lunch_break_out", type="string", example="14:00"),
     *             @OA\Property(property="friday_in_time", type="string", example="09:00"),
     *             @OA\Property(property="friday_out_time", type="string", example="17:00"),
     *             @OA\Property(property="friday_lunch_break", type="string", example="13:00"),
     *             @OA\Property(property="friday_lunch_break_out", type="string", example="14:00"),
     *             @OA\Property(property="saturday_in_time", type="string", example="09:00"),
     *             @OA\Property(property="saturday_out_time", type="string", example="17:00"),
     *             @OA\Property(property="saturday_lunch_break", type="string", example="13:00"),
     *             @OA\Property(property="saturday_lunch_break_out", type="string", example="14:00"),
     *             @OA\Property(property="sunday_in_time", type="string", example="09:00"),
     *             @OA\Property(property="sunday_out_time", type="string", example="17:00"),
     *             @OA\Property(property="sunday_lunch_break", type="string", example="13:00"),
     *             @OA\Property(property="sunday_lunch_break_out", type="string", example="14:00"),
     *             @OA\Property(property="in_time_beginning", type="string", example="08:30"),
     *             @OA\Property(property="in_time_end", type="string", example="10:00"),
     *             @OA\Property(property="out_time_beginning", type="string", example="16:30"),
     *             @OA\Property(property="out_time_end", type="string", example="18:30"),
     *             @OA\Property(property="late_allowance", type="integer", example=15),
     *             @OA\Property(property="break_start", type="string", example="13:00"),
     *             @OA\Property(property="break_end", type="string", example="14:00")
     *         )
     *     ),
     *     @OA\Response(response=201, description="تم الإضافة بنجاح"),
     *     @OA\Response(response=422, description="بيانات غير صالحة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لجلب الموظفين التابعين"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function store(StoreOfficeShiftRequest $request): JsonResponse
    {
        try {
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId(Auth::user());
            $dto = CreateOfficeShiftDTO::fromRequest($request->validated(), $effectiveCompanyId);
            $shift = $this->officeShiftService->createShift(Auth::user(), $dto);
            return $this->successResponse($shift, 'تم إضافة نوبة العمل بنجاح', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/office-shifts/{id}",
     *     summary="تعديل نوبة عمل",
     *     tags={"OfficeShifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="shift_name", type="string", example="Morning Shift Updated"),
     *             @OA\Property(property="hours_per_day", type="integer", example=8),
     *             @OA\Property(property="monday_in_time", type="string", example="09:00"),
     *             @OA\Property(property="monday_out_time", type="string", example="17:00"),
     *             @OA\Property(property="monday_lunch_break", type="string", example="13:00"),
     *             @OA\Property(property="monday_lunch_break_out", type="string", example="14:00"),
     *             @OA\Property(property="tuesday_in_time", type="string", example="09:00"),
     *             @OA\Property(property="tuesday_out_time", type="string", example="17:00"),
     *             @OA\Property(property="tuesday_lunch_break", type="string", example="13:00"),
     *             @OA\Property(property="tuesday_lunch_break_out", type="string", example="14:00"),
     *             @OA\Property(property="wednesday_in_time", type="string", example="09:00"),
     *             @OA\Property(property="wednesday_out_time", type="string", example="17:00"),
     *             @OA\Property(property="wednesday_lunch_break", type="string", example="13:00"),
     *             @OA\Property(property="wednesday_lunch_break_out", type="string", example="14:00"),
     *             @OA\Property(property="thursday_in_time", type="string", example="09:00"),
     *             @OA\Property(property="thursday_out_time", type="string", example="17:00"),
     *             @OA\Property(property="thursday_lunch_break", type="string", example="13:00"),
     *             @OA\Property(property="thursday_lunch_break_out", type="string", example="14:00"),
     *             @OA\Property(property="friday_in_time", type="string", example="09:00"),
     *             @OA\Property(property="friday_out_time", type="string", example="17:00"),
     *             @OA\Property(property="friday_lunch_break", type="string", example="13:00"),
     *             @OA\Property(property="friday_lunch_break_out", type="string", example="14:00"),
     *             @OA\Property(property="saturday_in_time", type="string", example="09:00"),
     *             @OA\Property(property="saturday_out_time", type="string", example="17:00"),
     *             @OA\Property(property="saturday_lunch_break", type="string", example="13:00"),
     *             @OA\Property(property="saturday_lunch_break_out", type="string", example="14:00"),
     *             @OA\Property(property="sunday_in_time", type="string", example="09:00"),
     *             @OA\Property(property="sunday_out_time", type="string", example="17:00"),
     *             @OA\Property(property="sunday_lunch_break", type="string", example="13:00"),
     *             @OA\Property(property="sunday_lunch_break_out", type="string", example="14:00"),
     *             @OA\Property(property="in_time_beginning", type="string", example="08:30"),
     *             @OA\Property(property="in_time_end", type="string", example="10:00"),
     *             @OA\Property(property="out_time_beginning", type="string", example="16:30"),
     *             @OA\Property(property="out_time_end", type="string", example="18:30"),
     *             @OA\Property(property="late_allowance", type="integer", example=15),
     *             @OA\Property(property="break_start", type="string", example="13:00"),
     *             @OA\Property(property="break_end", type="string", example="14:00")
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم التعديل بنجاح"),
     *     @OA\Response(response=422, description="بيانات غير صالحة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لجلب الموظفين التابعين"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function update(UpdateOfficeShiftRequest $request, int $id): JsonResponse
    {
        try {
            $dto = UpdateOfficeShiftDTO::fromRequest($request->validated(), $id);
            $this->officeShiftService->updateShift(Auth::user(), $id, $dto);
            return $this->successResponse(null, 'تم تحديث نوبة العمل بنجاح');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/office-shifts/{id}",
     *     summary="حذف نوبة عمل",
     *     tags={"OfficeShifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم الحذف بنجاح"),
     *     @OA\Response(response=422, description="بيانات غير صالحة"),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لجلب الموظفين التابعين"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->officeShiftService->deleteShift(Auth::user(), $id);
            return $this->successResponse(null, 'تم حذف نوبة العمل بنجاح');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }
}
