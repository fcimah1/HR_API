<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use App\DTOs\Attendance\AttendanceFilterDTO;
use App\DTOs\Attendance\CreateAttendanceDTO;
use App\DTOs\Attendance\GetAttendanceDetailsDTO;
use App\DTOs\Attendance\UpdateAttendanceDTO;
use App\Http\Requests\Attendance\ClockInRequest;
use App\Http\Requests\Attendance\ClockOutRequest;
use App\Http\Requests\Attendance\GetAttendanceByDayRequest;
use App\Http\Requests\Attendance\GetAttendanceDetailsRequest;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Attendance\UpdateAttendanceRequest;
use App\Services\SimplePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Attendance Management",
 *     description="إدارة سجلات الحضور"
 * )
 */
class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $attendanceService,
        private SimplePermissionService $permissionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/attendances",
     *     summary="جلب سجلات الحضور",
     *     tags={"Attendance Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         description="تصفية حسب رقم الموظف",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="تصفية حسب تاريخ البداية",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="تصفية حسب تاريخ النهاية",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="تصفية حسب اسم الموظف أو البريد الإلكتروني",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="ترتيب حسب العمود (created_at, status)",
     *         @OA\Schema(type="string", enum={"created_at", "status"})
     *     ),
     *     @OA\Parameter(
     *         name="sort_direction",
     *         in="query",
     *         description="ترتيب حسب الاتجاه (asc, desc)",
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب سجلات الحضور بنجاح",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم جلب سجلات الحضور بنجاح"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="time_attendance_id", type="integer", example=1012),
     *                     @OA\Property(property="company_id", type="integer", example=36),
     *                     @OA\Property(property="employee_id", type="integer", example=37),
     *                     @OA\Property(property="attendance_date", type="string", format="date", example="2025-12-01"),
     *                     @OA\Property(property="clock_in", type="string", example="2025-12-01 08:30:00"),
     *                     @OA\Property(property="clock_out", type="string", nullable=true, example="2025-12-01 17:00:00"),
     *                     @OA\Property(property="total_work", type="string", example="08:30"),
     *                     @OA\Property(property="total_rest", type="string", nullable=true, example="00:30"),
     *                     @OA\Property(property="attendance_status", type="string", example="Present"),
     *                     @OA\Property(property="status", type="string", example="Pending"),
     *                     @OA\Property(property="work_from_home", type="integer", example=0),
     *                     @OA\Property(property="lunch_break_in", type="string", nullable=true, example="2025-12-01 12:30:00"),
     *                     @OA\Property(property="lunch_break_out", type="string", nullable=true, example="2025-12-01 13:00:00"),
     *                     @OA\Property(
     *                         property="employee",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="user_id", type="integer", example=37),
     *                         @OA\Property(property="first_name", type="string", example="محمد"),
     *                         @OA\Property(property="last_name", type="string", example="أحمد"),
     *                         @OA\Property(property="email", type="string", example="m.ahmed@example.com")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=12),
     *                 @OA\Property(property="per_page", type="integer", example=20),
     *                 @OA\Property(property="total", type="integer", example=231),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=20),
     *                 @OA\Property(property="has_more_pages", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="غير مصرح - يجب تسجيل الدخول")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="غير مصرح لك بعرض سجلات الحضور",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="غير مصرح لك بعرض سجلات الحضور")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="خطأ في التحقق من البيانات",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم",
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
            $filters = AttendanceFilterDTO::fromRequest($request->all());
            $result = $this->attendanceService->getAttendanceRecords($filters, $user);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب سجلات الحضور بنجاح',
                ...$result
            ]);
        } catch (\Exception $e) {
            Log::error('AttendanceController::index failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 403);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/attendances/clock-in",
     *     summary="تسجيل الدخول للعمل",
     *     tags={"Attendance Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="latitude", type="string", example="24.7136", description="موقع الموظف - خط العرض"),
     *             @OA\Property(property="longitude", type="string", example="46.6753", description="موقع الموظف - خط الطول"),
     *             @OA\Property(property="work_from_home", type="boolean", example=false, description="العمل من المنزل")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="تم تسجيل الحضور بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تسجيل الحضور بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="غير مصرح - يجب تسجيل الدخول")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="فشل التحقق من البيانات",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="خطأ في الخادم")
     *         )
     *     )
     * )
     */
    public function clockIn(ClockInRequest $request)
    {
        try {
            $user = Auth::user();
            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
            $ipAddress = $request->ip();

            $dto = CreateAttendanceDTO::fromRequest(
                $request->validated(),
                $effectiveCompanyId,
                $user->user_id,
                $ipAddress
            );

            $attendance = $this->attendanceService->clockIn($dto);

            Log::info('AttendanceController::clockIn successful', [
                'user_id' => $user->user_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الحضور بنجاح',
                'data' => $attendance
            ], 201);
        } catch (\Exception $e) {
            Log::error('AttendanceController::clockIn failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/attendances/clock-out",
     *     summary="تسجيل الخروج",
     *     tags={"Attendance Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="latitude", type="string", example="24.7136", description="موقع الموظف - خط العرض"),
     *             @OA\Property(property="longitude", type="string", example="46.6753", description="موقع الموظف - خط الطول")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم تسجيل الانصراف بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم تسجيل الانصراف بنجاح"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="غير مصرح - يجب تسجيل الدخول")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="فشل التحقق من البيانات",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="خطأ في الخادم")
     *         )
     *     )
     * )
     */
    public function clockOut(ClockOutRequest $request)
    {
        try {
            $user = Auth::user();
            $ipAddress = $request->ip();
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');

            $attendance = $this->attendanceService->clockOut($user->user_id, $ipAddress, $latitude, $longitude);

            Log::info('AttendanceController::clockOut successful', [
                'user_id' => $user->user_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الانصراف بنجاح',
                'data' => $attendance
            ]);
        } catch (\Exception $e) {
            Log::error('AttendanceController::clockOut failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/attendances/lunch-break-in",
     *     summary="بدء استراحة الغداء",
     *     tags={"Attendance Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="تم بدء   استراحة الغداء"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="البيانات غير صحيحة"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="ليس لديك صلاحية لعرض تقرير حضور موظف آخر"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="فشل في التحقق من البيانات"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم"
     *     )
     * )
     */
    public function lunchBreakIn()
    {
        try {
            $user = Auth::user();
            $dto = UpdateAttendanceDTO::forLunchBreakIn();
            $attendance = $this->attendanceService->lunchBreakIn($user->user_id, $dto);

            return response()->json([
                'success' => true,
                'message' => 'تم بدء استراحة الغداء',
                'data' => $attendance
            ]);
        } catch (\Exception $e) {
            Log::error('AttendanceController::lunchBreakIn failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/attendances/lunch-break-out",
     *     summary="إنهاء استراحة الغداء",
     *     tags={"Attendance Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="تم إنهاء استراحة الغداء"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="البيانات غير صحيحة"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="ليس لديك صلاحية لعرض تقرير حضور موظف آخر"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="فشل في التحقق من البيانات"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم"
     *     )
     * )
     */
    public function lunchBreakOut()
    {
        try {
            $user = Auth::user();
            $dto = UpdateAttendanceDTO::forLunchBreakOut();
            $attendance = $this->attendanceService->lunchBreakOut($user->user_id, $dto);

            return response()->json([
                'success' => true,
                'message' => 'تم إنهاء استراحة الغداء',
                'data' => $attendance
            ]);
        } catch (\Exception $e) {
            Log::error('AttendanceController::lunchBreakOut failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/attendances/day",
     *     summary="عرض الحضور و الانصراف لموظف معين في تاريخ معين",
     *     tags={"Attendance Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         required=true,
     *         description="Employee ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="attendance_date",
     *         in="query",
     *         required=true,
     *         description="Attendance date",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم استرجاع حالة الحضور بنجاح"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="فشل في التحقق من البيانات"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="ليس لديك صلاحية لعرض تقرير حضور موظف آخر"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم"
     *     )
     * )
     */
    public function getAttendanceByDay(GetAttendanceByDayRequest $request)
    {
        try {
            $user = Auth::user();

            $status = $this->attendanceService->getAttendanceByDay($user, $request->validated());

            return response()->json([
                'success' => true,
                'data' => $status
            ]);
        } catch (\Exception $e) {
            Log::error('AttendanceController::getTodayStatus failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/attendances",
     *     summary="انشاء سجل حضور يدوي",
     *     tags={"Attendance Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "attendance_date", "clock_in", "office_shift_id", "status", "attendance_status"},
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="start_attendance_date", type="string", format="date", example="2026-02-01"),
     *             @OA\Property(property="end_attendance_date", type="string", format="date", example="2026-02-05"),
     *             @OA\Property(property="clock_in", type="string", format="time", example="09:00"),
     *             @OA\Property(property="clock_out", type="string", format="time", example="17:00"),
     *             @OA\Property(property="office_shift_id", type="integer", example=1),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="تم إنشاء سجل حضور يدوي بنجاح"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="فشل في التحقق من البيانات"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="ليس لديك صلاحية لعرض تقرير حضور موظف آخر"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم"
     *     )
     * )
     */
    public function store(StoreAttendanceRequest $request)
    {
        try {
            $user = Auth::user();

            $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);

            $attendance = $this->attendanceService->createManualAttendance($request->validated(), $effectiveCompanyId);

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة سجل الحضور بنجاح',
                'data' => $attendance
            ], 201);
        } catch (\Exception $e) {
            Log::error('AttendanceController::store failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/attendances/{id}",
     *     summary="تعديل سجل حضور",
     *     tags={"Attendance Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="clock_in", type="string", format="date-time", example="2023-10-01 09:00:00"),
     *             @OA\Property(property="clock_out", type="string", format="date-time", example="2023-10-01 17:00:00"),
     *             @OA\Property(property="status", type="string", enum={"Approved", "Pending", "Rejected"}, example="Approved"),
     *             @OA\Property(property="shift_id", type="integer", example=1),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم تحديث سجل الحضور بنجاح"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="البيانات غير صحيحة"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="ليس لديك صلاحية لعرض تقرير حضور موظف آخر"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="البيانات غير صحيحة"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم"
     *     )
     * )
     */
    public function update(UpdateAttendanceRequest $request, int $id)
    {
        try {
            $user = Auth::user();
            $dto = UpdateAttendanceDTO::fromUpdateRequest($request->validated());
            $attendance = $this->attendanceService->updateAttendance($id, $dto, $user);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث سجل الحضور بنجاح',
                'data' => $attendance
            ]);
        } catch (\Exception $e) {
            Log::error('AttendanceController::update failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/attendances/{id}",
     *     summary="حذف سجل حضور",
     *     tags={"Attendance Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم حذف سجل الحضور بنجاح"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="فشل في التحقق من البيانات"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="ليس لديك صلاحية لعرض تقرير حضور موظف آخر"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="البيانات غير صحيحة"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم"
     *     )
     * )
     */
    public function destroy(int $id)
    {
        try {
            $user = Auth::user();
            $this->attendanceService->deleteAttendance($id, $user);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف سجل الحضور بنجاح'
            ]);
        } catch (\Exception $e) {
            $status = 500;
            if ($e->getMessage() === 'سجل الحضور غير موجود') {
                $status = 404;
            }

            Log::error('AttendanceController::destroy failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $status);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/attendances/details",
     *     summary="جلب تفاصيل سجل حضور",
     *     tags={"Attendance Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         required=true,
     *         description="Date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="تم استرجاع تفاصيل الحضور بنجاح"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="فشل في التحقق من البيانات"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="ليس لديك صلاحية لعرض تقرير حضور موظف آخر"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم"
     *     )
     * )
     */
    public function getAttendanceDetails(GetAttendanceDetailsRequest $request)
    {
        try {
            $currentUser = Auth::user();
            $dto = GetAttendanceDetailsDTO::fromRequest($request);

            $details = $this->attendanceService->getAttendanceDetails($currentUser, $dto);

            if (!$details) {
                return response()->json([
                    'success' => false,
                    'message' => 'سجل الحضور غير موجود لهذا التاريخ'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $details
            ]);
        } catch (\Exception $e) {
            Log::error('AttendanceController::getAttendanceDetails failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/attendances/status",
     *     summary="جلب حالة الحضور",
     *     tags={"Attendance Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="تم استرجاع تفاصيل الحضور بنجاح"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="غير مصرح - يجب تسجيل الدخول"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="ليس لديك صلاحية لعرض تقرير حضور موظف آخر"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطأ في الخادم"
     *     )
     * )
     */
    public function getAttendanceStatus()
    {
        try {
            $currentUser = Auth::user();

            $details = $this->attendanceService->getAttendanceStatus($currentUser);

            if (!$details) {
                return response()->json([
                    'success' => false,
                    'message' => 'سجل الحضور غير موجود لهذا التاريخ'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $details
            ]);
        } catch (\Exception $e) {
            Log::error('AttendanceController::getAttendanceStatus failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
