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
use App\Http\Requests\Report\TerminationsReportRequest;
use App\Http\Requests\Report\TransfersReportRequest;
use App\Http\Requests\Report\ResidenceRenewalReportRequest;
use App\Http\Requests\Report\ExpiringContractsReportRequest;
use App\Http\Requests\Report\ExpiringDocumentsReportRequest;
use App\Http\Requests\Report\EmployeesByBranchReportRequest;
use App\Http\Requests\Report\EmployeesByCountryReportRequest;
use App\Http\Requests\Report\EndOfServiceRequest;

use App\Http\Requests\Report\GeneralReportRequest;
use App\Services\ReportService;
use App\DTOs\Report\AttendanceReportFilterDTO;
use App\Enums\AttendanceStatusEnum;
use App\Enums\JobTypeEnum;
use App\Enums\NumericalStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\StringStatusEnum;
use App\Enums\WagesTypeEnum;
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
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم"),  
     *     @OA\Response(response=404, description="Not found - غير موجود")
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
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
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
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم"),
     *     @OA\Response(response=404, description="Not found - غير موجود")
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
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
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
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم"),
     *     @OA\Response(response=404, description="Not found - غير موجود")
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
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
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
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم"),
     *     @OA\Response(response=404, description="Not found - غير موجود")
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
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
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
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم"),
     *     @OA\Response(response=404, description="Not found - غير موجود")
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
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
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
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم"),
     *     @OA\Response(response=404, description="Not found - غير موجود")
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
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
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
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم"),
     *     @OA\Response(response=404, description="Not found - غير موجود")
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
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
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
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم"),  
     *     @OA\Response(response=404, description="Not found - غير موجود")
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
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
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
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم"),
     *     @OA\Response(response=404, description="Not found - غير موجود")
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
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
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
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="الحالة: 0=قيد الانتظار, 1=مقبول, 2=مرفوض",
     *         @OA\Schema(type="integer", enum={0, 1, 2})
     *     ),
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
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم"),
     *     @OA\Response(response=404, description="Not found - غير موجود")
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
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/reports/resignations",
     *     summary="تقرير الاستقالات",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="الحالة: 0=قيد الانتظار, 1=مقبول, 2=مرفوض",
     *         @OA\Schema(type="integer", enum={0, 1, 2})
     *     ),
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
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم"),
     *     @OA\Response(response=404, description="Not found - غير موجود")
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
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/reports/terminations",
     *     summary="تقرير إنهاء الخدمة",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="الحالة: 0=قيد الانتظار, 1=مقبول, 2=مرفوض",
     *         @OA\Schema(type="integer", enum={0, 1, 2})
     *     ),
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
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم"),
     *     @OA\Response(response=404, description="Not found - غير موجود")
     * )
     */
    public function terminations(TerminationsReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $this->reportService->generateTerminationsReport($user, $companyId, $request->validated());
        } catch (\Exception $e) {
            Log::error('Failed to generate terminations report', [
                'error' => $e->getMessage()
            ]);

            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'فشل في إنشاء التقرير';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/reports/transfers",
     *     summary="تقرير التحويلات",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="start_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="الحالة: 0=قيد الانتظار, 1=مقبول, 2=مرفوض",
     *         @OA\Schema(type="integer", enum={0, 1, 2})
     *     ),
     *     @OA\Parameter(
     *         name="transfer_type",
     *         in="query",
     *         description="نوع التحويل",
     *         @OA\Schema(type="string", enum={"internal", "branch", "intercompany", "all"})
     *     ),
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
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم"),
     *     @OA\Response(response=404, description="Not found - غير موجود")
     * )
     */
    public function transfers(TransfersReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $this->reportService->generateTransfersReport($user, $companyId, $request->validated());
        } catch (\Exception $e) {
            Log::error('Failed to generate transfers report', [
                'error' => $e->getMessage()
            ]);

            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'فشل في إنشاء التقرير';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
        }
    }

    // ==========================================
    // تقارير الوثائق (Document Reports) - Placeholders
    // ==========================================

    /**
     * @OA\Get(
     *     path="/api/reports/residence-renewals",
     *     summary="تقرير تجديد الإقامة",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="employee_id", in="query", description="معرف الموظف", @OA\Schema(type="integer")),
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
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم"),
     *     @OA\Response(response=404, description="Not found - غير موجود")
     * )
     */
    public function residenceRenewals(ResidenceRenewalReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $this->reportService->generateResidenceRenewalReport($user, $companyId, $request->validated());
        } catch (\Exception $e) {
            Log::error('Failed to generate residence renewal report', [
                'error' => $e->getMessage()
            ]);

            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'فشل في إنشاء التقرير';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/reports/expiring-contracts",
     *     summary="تقرير العقود قريبة الانتهاء - قيد التطوير",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         required=true,
     *         description="تاريخ الانتهاء قبل (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
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
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم"),
     *     @OA\Response(response=404, description="Not found - غير موجود")
     * )
     */
    public function expiringContracts(ExpiringContractsReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $this->reportService->generateExpiringContractsReport($user, $companyId, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Expiring Contracts Report Error: ' . $e->getMessage());

            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'فشل في إنشاء التقرير';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/reports/expiring-documents",
     *     summary="تقرير الوثائق قريبة الانتهاء - قيد التطوير",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         required=true,
     *         description="تاريخ الانتهاء قبل (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
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
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم"),
     *     @OA\Response(response=404, description="Not found - غير موجود")
     * )
     */
    public function expiringDocuments(ExpiringDocumentsReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $this->reportService->generateExpiringDocumentsReport($user, $companyId, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Expiring Documents Report Error: ' . $e->getMessage());

            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'فشل في إنشاء التقرير';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
        }
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
     *     @OA\Parameter(
     *         name="branch_id",
     *         in="query",
     *         description="معرف الفرع (اختياري)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="الحالة: active, inactive, left, all",
     *         required=false,
     *         @OA\Schema(type="string", enum={"active", "inactive", "left", "all"})
     *     ),

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
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم"),
     *     @OA\Response(response=404, description="Not found - غير موجود")
     * )
     */
    public function employeesByBranch(EmployeesByBranchReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $this->reportService->generateEmployeesByBranchReport($user, $companyId, $request->validated());
        } catch (\Exception $e) {
            Log::error('Failed to generate employees by branch report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'فشل في إنشاء التقرير';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/reports/employees-by-country",
     *     summary="تقرير الموظفين حسب الدولة",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="country_id", in="query", description="(بالاسم او بالرقم) الدولة", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", description="الحالة", @OA\Schema(type="string", enum={"active","inactive"})),
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
     *     @OA\Response(response=500, description="Server error - خطأ في الخادم"),
     *     @OA\Response(response=404, description="Not found - غير موجود")
     * )
     */
    public function employeesByCountry(EmployeesByCountryReportRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $this->reportService->generateEmployeesByCountryReport($user, $companyId, $request->validated());
        } catch (\Exception $e) {
            Log::error('Failed to generate employees by country report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'فشل في إنشاء التقرير';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
        }
    }


    /**
     * @OA\Get(
     *     path="/api/reports/options",
     *     summary="استرجاع خيارات التقارير",
     *     description="يرجع جميع القيم المستخدمة في التقارير (حالات الحضور، نوع الوظيفة، طرق الدفع، نوع الراتب)",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="تم استرجاع الخيارات بنجاح",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="attendance_status",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="value", type="string", example="Present"),
     *                         @OA\Property(property="label", type="string", example="حاضر")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="job_type",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="value", type="string", example="permanent"),
     *                         @OA\Property(property="label", type="string", example="دائمة")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="payment_method",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="value", type="string", example="CASH"),
     *                         @OA\Property(property="label", type="string", example="نقد")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="wages_type",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="value", type="integer", example=1),
     *                         @OA\Property(property="label", type="string", example="شهري")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="غير مصرح")
     * )
     */
    public function options(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'status' => NumericalStatusEnum::toArray(),
                'attendance_status' => AttendanceStatusEnum::toArray(),
                'job_type' => JobTypeEnum::toArray(),
                'payment_method' => PaymentMethodEnum::toArray(),
                'wages_type' => WagesTypeEnum::toArray(),
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/reports/end-of-service",
     *     summary="تقرير نهاية الخدمة",
     *     description="استخراج تقرير نهاية الخدمة للموظفين مع دعم التصدير PDF/Excel",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         required=false,
     *         description="فلترة حسب موظف معين",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="نجاح العملية",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function endOfService(EndOfServiceRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

            $this->reportService->endOfService($user, $companyId, $request->validated());

            // PDF is downloaded directly
        } catch (\Exception $e) {
            Log::error('Failed to generate end of service report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'فشل في إنشاء التقرير';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $e instanceof \InvalidArgumentException ? 422 : 500);
        }
    }
}
