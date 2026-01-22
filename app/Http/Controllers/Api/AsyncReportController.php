<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportJob;
use App\Models\GeneratedReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Queue-based Async Report Generation Controller
 */
class AsyncReportController extends Controller
{
    // /**
    //  * Request async report generation
    //  * 
    //  * @OA\Post(
    //  *     path="/api/reports/generate-async/{type}",
    //  *     summary="طلب توليد تقرير في الخلفية",
    //  *     tags={"Reports - Async"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(
    //  *         name="type",
    //  *         in="path",
    //  *         required=true,
    //  *         description="نوع التقرير",
    //  *         @OA\Schema(type="string")
    //  *     ),
    //  *     @OA\RequestBody(
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="month", type="string", example="2026-01"),
    //  *             @OA\Property(property="employee_id", type="integer"),
    //  *             @OA\Property(property="branch_id", type="integer")
    //  *         )
    //  *     ),
    //  *     @OA\Response(response=200, description="تم إضافة التقرير للقائمة"),
    //  *     @OA\Response(response=401, description="Unauthorized")
    //  * )
    //  */
    // public function generateAsync(Request $request, string $type): JsonResponse
    // {
    //     $user = $request->user();
    //     $companyId = ($user->user_type === 'company' || $user->company_id === 0) ? $user->user_id : $user->company_id;

    //     // Validate report type
    //     $validTypes = [
    //         'attendance_monthly',
    //         'attendance_first_last',
    //         'attendance_time_records',
    //         'attendance_date_range',
    //         'timesheet',
    //         'loan',
    //         'leave',
    //         'payroll',
    //         'terminations',
    //         'resignations',
    //         'transfers',
    //         'residence_renewal',
    //         'employees_by_branch',
    //         'employees_by_country',
    //         'awards',
    //         'promotions',
    //         'expiring_contracts',
    //         'expiring_documents',
    //         'end_of_service'
    //     ];

    //     if (!in_array($type, $validTypes)) {
    //         return response()->json([
    //             'message' => 'نوع تقرير غير صحيح',
    //             'valid_types' => $validTypes
    //         ], 400);
    //     }

    //     // Generate report title
    //     $title = $this->getReportTitle($type);

    //     // Create report record
    //     $report = GeneratedReport::create([
    //         'user_id' => $user->user_id,
    //         'company_id' => $companyId,
    //         'report_type' => $type,
    //         'report_title' => $title,
    //         'status' => 'pending',
    //         'filters' => $request->all(),
    //     ]);

    //     // Dispatch job to queue
    //     GenerateReportJob::dispatch(
    //         $report->report_id,
    //         $type,
    //         $user->user_id,
    //         $companyId,
    //         $request->all(),
    //         $title,
    //         $request->dateRange ?? '',
    //         $request->statusText ?? '',
    //         $request->transferTypeText ?? '',
    //         $request->transferType ?? ''
    //     );

    //     return response()->json([
    //         'message' => 'تم إضافة التقرير للمعالجة',
    //         'report_id' => $report->report_id,
    //         'status' => 'pending',
    //         'estimated_time' => '1-5 دقائق'
    //     ]);
    // }

    // /**
    //  * Get user's generated reports list
    //  * 
    //  * @OA\Get(
    //  *     path="/api/reports/generated",
    //  *     summary="قائمة التقارير المولدة",
    //  *     tags={"Reports - Async"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Response(response=200, description="قائمة التقارير")
    //  * )
    //  */
    // public function generatedReports(Request $request): JsonResponse
    // {
    //     $user = $request->user();

    //     $reports = GeneratedReport::where('user_id', $user->user_id)
    //         ->orderBy('created_at', 'desc')
    //         ->paginate(20);

    //     // Add formatted file size to each report
    //     $reports->getCollection()->transform(function ($report) {
    //         $report->formatted_size = $report->getFormattedFileSize();
    //         return $report;
    //     });

    //     return response()->json($reports);
    // }

    // /**
    //  * Download a generated report
    //  * 
    //  * @OA\Get(
    //  *     path="/api/reports/generated/{id}/download",
    //  *     summary="تحميل تقرير مولد",
    //  *     tags={"Reports - Async"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(name="id", in="path", required=true),
    //  *     @OA\Response(response=200, description="ملف PDF"),
    //  *     @OA\Response(response=404, description="التقرير غير موجود")
    //  * )
    //  */
    // public function downloadGenerated(int $id): mixed
    // {
    //     $report = GeneratedReport::findOrFail($id);

    //     // Check permissions
    //     if ($report->user_id !== Auth::user()->user_id) {
    //         abort(403, 'غير مصرح لك بتحميل هذا التقرير');
    //     }

    //     if (!$report->isCompleted()) {
    //         return response()->json([
    //             'message' => 'التقرير غير جاهز بعد',
    //             'status' => $report->status,
    //             'started_at' => $report->started_at
    //         ], 400);
    //     }

    //     $path = $report->getFileFullPath();

    //     if (!file_exists($path)) {
    //         return response()->json([
    //             'message' => 'الملف غير موجود'
    //         ], 404);
    //     }

    //     return response()->download($path);
    // }

    // /**
    //  * Delete a generated report
    //  * 
    //  * @OA\Delete(
    //  *     path="/api/reports/generated/{id}",
    //  *     summary="حذف تقرير مولد",
    //  *     tags={"Reports - Async"},
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\Parameter(name="id", in="path", required=true),
    //  *     @OA\Response(response=200, description="تم الحذف")
    //  * )
    //  */
    // public function deleteGenerated(int $id): JsonResponse
    // {
    //     $report = GeneratedReport::findOrFail($id);

    //     // Check permissions
    //     if ($report->user_id !== Auth::user()->user_id) {
    //         abort(403, 'غير مصرح لك بحذف هذا التقرير');
    //     }

    //     // Delete file if exists
    //     if ($report->file_path && file_exists($report->getFileFullPath())) {
    //         unlink($report->getFileFullPath());
    //     }

    //     // Delete record
    //     $report->delete();

    //     return response()->json([
    //         'message' => 'تم حذف التقرير بنجاح'
    //     ]);
    // }

    // /**
    //  * Get report title based on type
    //  */
    // private function getReportTitle(string $type): string
    // {
    //     $titles = [
    //         'attendance_monthly' => 'تقرير الحضور الشهري',
    //         'attendance_first_last' => 'تقرير أول وآخر حضور',
    //         'attendance_time_records' => 'تقرير سجلات الأوقات',
    //         'attendance_date_range' => 'تقرير الحضور حسب التاريخ',
    //         'timesheet' => 'تقرير Timesheet',
    //         'loan' => 'تقرير السلف',
    //         'leave' => 'تقرير الإجازات',
    //         'payroll' => 'تقرير الرواتب',
    //         'terminations' => 'تقرير إنهاء الخدمة',
    //         'resignations' => 'تقرير الاستقالات',
    //         'transfers' => 'تقرير النقل',
    //         'residence_renewal' => 'تقرير تجديد الإقامة',
    //         'employees_by_branch' => 'تقرير الموظفين حسب الفرع',
    //         'employees_by_country' => 'تقرير الموظفين حسب الدولة',
    //         'awards' => 'تقرير الجوائز',
    //         'promotions' => 'تقرير الترقيات',
    //         'expiring_contracts' => 'تقرير العقود منتهية الصلاحية',
    //         'expiring_documents' => 'تقرير المستندات منتهية الصلاحية',
    //         'end_of_service' => 'تقرير نهاية الخدمة',
    //     ];

    //     return $titles[$type] ?? 'تقرير';
    // }
}
