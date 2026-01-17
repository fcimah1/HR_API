<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\AttendanceMonthlyReportRequest;
use App\Http\Requests\Report\AttendanceDateRangeReportRequest;
use App\Http\Requests\Report\AttendanceFirstLastReportRequest;
use App\Http\Requests\Report\AwardsReportRequest;
use App\Http\Requests\Report\PromotionsReportRequest;
use App\Http\Requests\Report\ResignationsReportRequest;

use App\Http\Requests\Report\GeneralReportRequest;
use App\Services\ReportService;
use App\DTOs\Report\AttendanceReportFilterDTO;
use App\Http\Requests\Report\AttendanceTimeLogsReportRequest;
use App\Http\Requests\Report\LoanReportRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Reports",
 *     description="التقارير - Reports Management"
 * )
 */
class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService
    ) {}

    // ==========================================
    // تقارير الحضور والانصراف (Attendance Reports)
    // ==========================================

    /**
     * @OA\Get(
     *     path="/api/reports/attendance/monthly",
     *     summary="تقرير الحضور الشهري",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="start_date", required=true, in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", required=true, in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="PDF file"),
     *     @OA\Response(response=401, description="يجب تسجيل الدخول - غير مصرح"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function attendanceMonthly(AttendanceFirstLastReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $filters = AttendanceReportFilterDTO::fromRequest(
                $request->validated(),
                $companyId
            );

            $this->reportService->generateAttendanceMonthlyReport($user, $filters);
        } catch (\Exception $e) {
            Log::error('Failed to generate attendance monthly report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Show specific error message if it's a validation/logic error
            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'فشل في إنشاء التقرير';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/reports/attendance/first-last",
     *     summary="تقرير أول وآخر حضور/انصراف",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="start_date", required=true, in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", required=true, in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="PDF file"),
     *     @OA\Response(response=401, description="يجب تسجيل الدخول - غير مصرح"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function attendanceFirstLast(AttendanceFirstLastReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $filters = AttendanceReportFilterDTO::fromRequest(
                $request->validated(),
                $companyId
            );

            $this->reportService->generateAttendanceFirstLastReport($user, $filters);
        } catch (\Exception $e) {
            Log::error('Failed to generate attendance first-last report', [
                'error' => $e->getMessage()
            ]);

            // Show specific error message if it's a validation/logic error
            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'فشل في إنشاء التقرير';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/reports/attendance/time-records",
     *     summary="تقرير سجلات الوقت",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="start_date", required=true, in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", required=true, in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="employee_id", required=true, in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="PDF file"),
     *     @OA\Response(response=401, description="يجب تسجيل الدخول - غير مصرح"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function attendanceTimeRecords(AttendanceTimeLogsReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $filters = AttendanceReportFilterDTO::fromRequest(
                $request->validated(),
                $companyId
            );

            $this->reportService->generateAttendanceTimeRecordsReport($user, $filters);
        } catch (\Exception $e) {
            Log::error('Failed to generate time records report', [
                'error' => $e->getMessage()
            ]);

            // Show specific error message if it's a validation/logic error
            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'فشل في إنشاء التقرير';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/reports/attendance/date-range",
     *     summary="تقرير الحضور بنطاق زمني",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="start_date", required=true, description="تاريخ البداية", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", required=true, description="تاريخ النهاية", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="employee_id", required=true, description="الموظف", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="PDF file"),
     *     @OA\Response(response=401, description="يجب تسجيل الدخول - غير مصرح"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function attendanceDateRange(AttendanceDateRangeReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $filters = AttendanceReportFilterDTO::fromRequest(
                $request->validated(),
                $companyId
            );

            $this->reportService->generateAttendanceDateRangeReport($user, $filters);
        } catch (\Exception $e) {
            Log::error('Failed to generate attendance date range report', [
                'error' => $e->getMessage()
            ]);

            // Show specific error message if it's a validation/logic error
            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'فشل في إنشاء التقرير';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/reports/timesheet",
     *     summary="تقرير سجل الدوام (Timesheet)",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="start_date", required=true, in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", required=true, in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="employee_id", required=false, in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="PDF file"),
     *     @OA\Response(response=401, description="يجب تسجيل الدخول - غير مصرح"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function timesheet(AttendanceFirstLastReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $filters = AttendanceReportFilterDTO::fromRequest(
                $request->validated(),
                $companyId
            );

            $this->reportService->generateTimesheetReport($user, $filters);
        } catch (\Exception $e) {
            Log::error('Failed to generate timesheet report', [
                'error' => $e->getMessage()
            ]);

            // Show specific error message if it's a validation/logic error
            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'فشل في إنشاء التقرير';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    // ==========================================
    // التقارير المالية (Financial Reports)
    // ==========================================

    /**
     * @OA\Get(
     *     path="/api/reports/payroll",
     *     summary="تقرير الرواتب الشهري",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="payment_date", in="query", required=true, @OA\Schema(type="string", example="2026-01", description="YYYY-MM")),
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="payment_method", in="query", @OA\Schema(type="string", enum={"cash", "bank", "all"})),
     *     @OA\Parameter(name="job_type", in="query", @OA\Schema(type="string", enum={"part_time", "permanent", "contract", "probation", "all"})),
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="PDF file"),
     *     @OA\Response(response=401, description="يجب تسجيل الدخول - غير مصرح"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function payroll(\App\Http\Requests\Report\PayrollReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $this->reportService->generatePayrollReport($user, $companyId, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to generate payroll report', [
                'error' => $e->getMessage()
            ]);

            // Show specific error message if it's a validation/logic error
            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'فشل في إنشاء التقرير';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/reports/loans",
     *     summary="تقرير السلف والقروض",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="start_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="PDF file"),
     *     @OA\Response(response=401, description="يجب تسجيل الدخول - غير مصرح"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function loans(LoanReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $this->reportService->generateLoanReport($user, $companyId, $request->validated());
        } catch (\Exception $e) {
            Log::error('Failed to generate loans report', [
                'error' => $e->getMessage()
            ]);

            // Show specific error message if it's a validation/logic error
            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'فشل في إنشاء التقرير';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    // ==========================================
    // تقارير الموارد البشرية (HR Reports)
    // ==========================================

    /**
     * @OA\Get(
     *     path="/api/reports/leaves",
     *     summary="تقرير الإجازات",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="year", in="query", @OA\Schema(type="integer", example=2026)),
     *     @OA\Parameter(name="leave_type", in="query", @OA\Schema(type="integer", description="Leave Type Constants ID")),
     *     @OA\Parameter(name="duration_type", in="query", @OA\Schema(type="string", enum={"daily", "hourly"}, default="hourly")),
     *     @OA\Response(response=200, description="PDF file"),
     *     @OA\Response(response=401, description="يجب تسجيل الدخول - غير مصرح"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function leaves(GeneralReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $this->reportService->generateLeaveReport($user, $companyId, $request->validated());
        } catch (\Exception $e) {
            Log::error('Failed to generate leaves report', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء التقرير',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/reports/awards",
     *     summary="تقرير المكافآت",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="start_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="PDF file"),
     *     @OA\Response(response=401, description="يجب تسجيل الدخول - غير مصرح"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function awards(AwardsReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $this->reportService->generateAwardsReport($user, $companyId, $request->validated());
        } catch (\Exception $e) {
            Log::error('Failed to generate awards report', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء التقرير',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/reports/promotions",
     *     summary="تقرير الترقيات",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="start_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="integer", enum={0, 1, 2}, description="0: Pending, 1: Accepted, 2: Rejected")),
     *     @OA\Response(response=200, description="PDF file"),
     *     @OA\Response(response=401, description="يجب تسجيل الدخول - غير مصرح"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function promotions(PromotionsReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $this->reportService->generatePromotionsReport($user, $companyId, $request->validated());
        } catch (\Exception $e) {
            Log::error('Failed to generate promotions report', [
                'error' => $e->getMessage()
            ]);

            // Show specific error message if it's a validation/logic error
            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'فشل في إنشاء التقرير';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/reports/resignations",
     *     summary="تقرير الاستقالات",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="start_date", required=true, in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", required=true, in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="PDF file"),
     *     @OA\Response(response=401, description="يجب تسجيل الدخول - غير مصرح"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function resignations(ResignationsReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $this->reportService->generateResignationsReport($user, $companyId, $request->validated());
        } catch (\Exception $e) {
            Log::error('Failed to generate resignations report', [
                'error' => $e->getMessage()
            ]);

            // Show specific error message if it's a validation/logic error
            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'فشل في إنشاء التقرير';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/reports/terminations",
     *     summary="تقرير إنهاء الخدمة - قيد التطوير",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="PDF file"),
     *     @OA\Response(response=401, description="يجب تسجيل الدخول - غير مصرح"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function terminations(Request $request): JsonResponse
    {
        $user = Auth::user();
        $result = $this->reportService->generateTerminationsReport($user, $user->company_id);

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/reports/transfers",
     *     summary="تقرير التحويلات",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="start_date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="PDF file"),
     *     @OA\Response(response=401, description="يجب تسجيل الدخول - غير مصرح"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function transfers(GeneralReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $this->reportService->generateTransfersReport($user, $companyId, $request->validated());
        } catch (\Exception $e) {
            Log::error('Failed to generate transfers report', [
                'error' => $e->getMessage()
            ]);

            // Show specific error message if it's a validation/logic error
            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'فشل في إنشاء التقرير';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    // ==========================================
    // تقارير الوثائق (Document Reports) - Placeholders
    // ==========================================

    /**
     * @OA\Get(
     *     path="/api/reports/residence-renewals",
     *     summary="تقرير تجديد الإقامة - قيد التطوير",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="PDF file"),
     *     @OA\Response(response=401, description="يجب تسجيل الدخول - غير مصرح"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function residenceRenewals(Request $request): JsonResponse
    {
        $user = Auth::user();
        $result = $this->reportService->generateResidenceRenewalReport($user, $user->company_id);

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/reports/expiring-contracts",
     *     summary="تقرير العقود قريبة الانتهاء - قيد التطوير",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="PDF file"),
     *     @OA\Response(response=401, description="يجب تسجيل الدخول - غير مصرح"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function expiringContracts(Request $request): JsonResponse
    {
        $user = Auth::user();
        $result = $this->reportService->generateExpiringContractsReport($user, $user->company_id);

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/reports/expiring-documents",
     *     summary="تقرير الوثائق قريبة الانتهاء - قيد التطوير",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="PDF file"),
     *     @OA\Response(response=401, description="يجب تسجيل الدخول - غير مصرح"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function expiringDocuments(Request $request): JsonResponse
    {
        $user = Auth::user();
        $result = $this->reportService->generateExpiringDocumentsReport($user, $user->company_id);

        return response()->json($result);
    }

    // ==========================================
    // تقارير الموظفين (Employee Reports)
    // ==========================================

    /**
     * @OA\Get(
     *     path="/api/reports/employees-by-branch",
     *     summary="تقرير الموظفين حسب الفرع",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"active","inactive"})),
     *     @OA\Response(response=200, description="PDF file"),
     *     @OA\Response(response=401, description="يجب تسجيل الدخول - غير مصرح"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function employeesByBranch(GeneralReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $this->reportService->generateEmployeesByBranchReport($user, $companyId, $request->validated());
        } catch (\Exception $e) {
            Log::error('Failed to generate employees by branch report', [
                'error' => $e->getMessage()
            ]);

            // Show specific error message if it's a validation/logic error
            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'فشل في إنشاء التقرير';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/reports/employees-by-country",
     *     summary="تقرير الموظفين حسب الدولة",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="country_id", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"active","inactive"})),
     *     @OA\Response(response=200, description="PDF file"),
     *     @OA\Response(response=401, description="يجب تسجيل الدخول - غير مصرح"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function employeesByCountry(GeneralReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $this->reportService->generateEmployeesByCountryReport($user, $companyId, $request->validated());
        } catch (\Exception $e) {
            Log::error('Failed to generate employees by country report', [
                'error' => $e->getMessage()
            ]);

            // Show specific error message if it's a validation/logic error
            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'فشل في إنشاء التقرير';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/reports/end-of-service",
     *     summary="تقرير حسابات نهاية الخدمة - قيد التطوير",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="PDF file"),
     *     @OA\Response(response=401, description="يجب تسجيل الدخول - غير مصرح"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - خطأ في التحقق",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="فشل التحقق من البيانات"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم")
     * )
     */
    public function endOfService(Request $request): JsonResponse
    {
        $user = Auth::user();
        $result = $this->reportService->generateEndOfServiceReport($user, $user->company_id);

        return response()->json($result);
    }
}
