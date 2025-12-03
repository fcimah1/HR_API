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
use App\Http\Requests\Attendance\GetAttendanceDetailsRequest;
use App\Http\Requests\Attendance\UpdateAttendanceRequest;
use App\Services\SimplePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Attendance Management",
 *     description="Attendance and timesheet management"
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
     *     summary="Get attendance records",
     *     tags={"Attendance Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         description="Filter by employee ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Filter from date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Filter to date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by employee name or email",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by column (created_at, status)",
     *         @OA\Schema(type="string", enum={"created_at", "status"})
     *     ),
     *     @OA\Parameter(
     *         name="sort_direction",
     *         in="query",
     *         description="Sort direction (asc, desc)",
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Attendance records retrieved successfully"
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
     *     summary="Clock in for the day",
     *     tags={"Attendance Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="latitude", type="string", example="24.7136"),
     *             @OA\Property(property="longitude", type="string", example="46.6753"),
     *             @OA\Property(property="work_from_home", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Clock in successful"
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
     *     summary="Clock out for the day",
     *     tags={"Attendance Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="latitude", type="string", example="24.7136"),
     *             @OA\Property(property="longitude", type="string", example="46.6753")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Clock out successful"
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
     *     summary="Start lunch break",
     *     tags={"Attendance Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lunch break started"
     *     )
     * )
     */
    public function lunchBreakIn()
    {
        try {
            $user = Auth::user();
            $attendance = $this->attendanceService->lunchBreakIn($user->user_id);

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
     *     summary="End lunch break",
     *     tags={"Attendance Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lunch break ended"
     *     )
     * )
     */
    public function lunchBreakOut()
    {
        try {
            $user = Auth::user();
            $attendance = $this->attendanceService->lunchBreakOut($user->user_id);

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
     *     path="/api/attendances/today",
     *     summary="Get today's attendance status",
     *     tags={"Attendance Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         required=false,
     *         description="Employee ID (optional, requires permission to view others)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Today's status retrieved successfully"
     *     )
     * )
     */
    public function getTodayStatus(Request $request)
    {
        try {
            $user = Auth::user();
            $employeeId = $request->input('employee_id');

            $status = $this->attendanceService->getTodayStatus($user, $employeeId ? (int)$employeeId : null);

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
     * @OA\Put(
     *     path="/api/attendances/{id}",
     *     summary="Update attendance record (admin only)",
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
     *         description="Attendance updated"
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
     *     summary="Delete attendance record (admin only)",
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
     *         description="Attendance deleted"
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
            Log::error('AttendanceController::destroy failed', [
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
     *     path="/api/attendances/monthly-report",
     *     summary="Get monthly attendance report",
     *     tags={"Attendance Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         required=false,
     *         description="Employee ID (optional, requires permission to view others)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="month",
     *         in="query",
     *         required=true,
     *         description="Month in YYYY-MM format",
     *         @OA\Schema(type="string", example="2025-11")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Monthly report retrieved"
     *     )
     * )
     */
    public function getMonthlyReport(Request $request)
    {
        try {
            $user = Auth::user();
            $employeeId = $request->input('employee_id');
            $month = $request->input('month');

            if (!$month) {
                return response()->json([
                    'success' => false,
                    'message' => 'يجب تحديد الشهر'
                ], 422);
            }

            $report = $this->attendanceService->getMonthlyReport($user, $month, $employeeId ? (int)$employeeId : null);

            return response()->json([
                'success' => true,
                'data' => $report
            ]);
        } catch (\Exception $e) {
            Log::error('AttendanceController::getMonthlyReport failed', [
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
     *     path="/api/attendances/details",
     *     summary="Get attendance details for specific user and date",
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
     *         description="Attendance details retrieved"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Attendance record not found"
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
}
