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
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لجلب الموظفين التابعين"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = OfficeShiftFilterDTO::fromArray($request->all());
            $shifts = $this->officeShiftService->getPaginatedShifts(Auth::user(), $filters);
            if(!$shifts){
                Log::error('Error getting office shifts:' ,[
                    'success' => false,
                    'message' => 'حدث خطأ أثناء جلب نوبات العمل',
                    'error' => 'حدث خطأ أثناء جلب نوبات العمل',
                ]);
                return $this->errorResponse('حدث خطأ أثناء جلب نوبات العمل', 400);
            }
            Log::info('Office shifts retrieved successfully:' ,[
                'success' => true,
                'message' => 'تم جلب نوبات العمل بنجاح',
                'data' => $shifts,
            ]);
            return $this->successResponse($shifts, 'تم جلب نوبات العمل بنجاح');
        } catch (\Exception $e) {
            Log::error('Error getting office shifts:' ,[
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب نوبات العمل',
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/office-shifts/{id}",
     *     summary="جلب تفاصيل نوبة عمل",
     *     tags={"OfficeShifts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="تم بنجاح"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لجلب الموظفين التابعين"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
        $shift = $this->officeShiftService->getShiftDetails(Auth::user(), $id);
        if (!$shift) {
            Log::error('Error getting office shift:' ,[
                'success' => false,
                'message' => 'نوبة العمل غير موجودة',
                'error' => 'نوبة العمل غير موجودة',
            ]);
            return $this->errorResponse('نوبة العمل غير موجودة', 404);
        }
        Log::info('Office shift details retrieved successfully:' ,[
            'success' => true,
            'message' => 'تم جلب تفاصيل نوبة العمل بنجاح',
            'data' => $shift,
        ]);
        return $this->successResponse($shift, 'تم جلب تفاصيل نوبة العمل بنجاح');
        } catch (\Exception $e) {
            Log::error('Error getting office shift:' ,[
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب تفاصيل نوبة العمل',
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse($e->getMessage(), 500);
        }
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
     *             @OA\Property(property="in_time_beginning", type="string", format="time", example="08:30"),
     *             @OA\Property(property="in_time_end", type="string", format="time", example="10:00"),
     *             @OA\Property(property="out_time_beginning", type="string", format="time", example="16:30"),
     *             @OA\Property(property="out_time_end", type="string", format="time", example="18:30"),
     *             @OA\Property(property="late_allowance", type="integer", example=15),
     *             @OA\Property(property="break_start", type="string", format="time", example="13:00"),
     *             @OA\Property(property="break_end", type="string", format="time", example="14:00")
     *         )
     *     ),
     *     @OA\Response(response=201, description="تم الإضافة بنجاح"),
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
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
            if(!$shift){
                Log::error('Error creating office shift:' ,[
                    'success' => false,
                    'message' => 'حدث خطأ أثناء إضافة نوبة العمل',
                    'error' => 'حدث خطأ أثناء إضافة نوبة العمل',
                ]);
                return $this->errorResponse('حدث خطأ أثناء إضافة نوبة العمل', 404);
            }
            Log::info('Office shift created successfully:' ,[
                'success' => true,
                'message' => 'تم إضافة نوبة العمل بنجاح',
                'data' => $shift,
            ]);
            return $this->successResponse($shift, 'تم إضافة نوبة العمل بنجاح', 201);

        } catch (\Exception $e) {
            Log::error('Error creating office shift:' ,[
                'success' => false,
                'message' => 'حدث خطأ أثناء إضافة نوبة العمل',
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse($e->getMessage(), 500);
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
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لجلب الموظفين التابعين"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function update(UpdateOfficeShiftRequest $request, int $id): JsonResponse
    {
        try {
            $dto = UpdateOfficeShiftDTO::fromRequest($request->validated(), $id);
            $updatedShift = $this->officeShiftService->updateShift(Auth::user(), $id, $dto);
            if(!$updatedShift){
                Log::error('Error updating office shift:' ,[
                    'success' => false,
                    'message' => 'حدث خطأ أثناء تحديث نوبة العمل',
                    'error' => 'حدث خطأ أثناء تحديث نوبة العمل',
                ]);
                return $this->errorResponse('حدث خطأ أثناء تحديث نوبة العمل', 404);
            }
            Log::info('Office shift updated successfully:' ,[
                'success' => true,
                'message' => 'تم تحديث نوبة العمل بنجاح',
                'data' => $updatedShift,
            ]);
            return $this->successResponse(null, 'تم تحديث نوبة العمل بنجاح');
        } catch (\Exception $e) {
            Log::error('Error updating office shift:' ,[
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث نوبة العمل',
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse($e->getMessage(), 500);
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
     *     @OA\Response(response=422, description=" فشل التحقق من البيانات "),
     *     @OA\Response(response=401, description="غير مصرح - يجب تسجيل الدخول"),
     *     @OA\Response(response=404, description="الموظف أو البيانات غير موجودة أو ليس لديك صلاحية لجلب الموظفين التابعين"),
     *     @OA\Response(response=500, description="خطأ في الخادم")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $deletedShift = $this->officeShiftService->deleteShift(Auth::user(), $id);
            if(!$deletedShift){
                Log::error('Error deleting office shift:' ,[
                    'success' => false,
                    'message' => 'حدث خطأ أثناء حذف نوبة العمل',
                    'error' => 'حدث خطأ أثناء حذف نوبة العمل',
                ]);
                return $this->errorResponse('حدث خطأ أثناء حذف نوبة العمل', 404);
            }
            Log::info('Office shift deleted successfully:' ,[
                'success' => true,
                'message' => 'تم حذف نوبة العمل بنجاح',
                'data' => $deletedShift,
            ]);
            return $this->successResponse(null, 'تم حذف نوبة العمل بنجاح');
        } catch (\Exception $e) {
            Log::error('Error deleting office shift:' ,[
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف نوبة العمل',
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
