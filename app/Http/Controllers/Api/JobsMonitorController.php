<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SimplePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * لوحة تحكم للـ Queue Jobs
 * Jobs Monitor Dashboard Controller
 */
class JobsMonitorController extends Controller
{
    public function __construct(
        private readonly SimplePermissionService $permissionService
    ) {}

    /**
     * الحصول على إحصائيات الـ Jobs
     * @OA\Get(
     *     path="/api/jobs/stats",
     *     summary="Get queue jobs statistics",
     *     tags={"Jobs Monitor"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Statistics retrieved successfully")
     * )
     */
    public function getStats(Request $request): JsonResponse
    {
        $user = $request->user();

        // فقط الشركة يمكنها مراقبة الـ Jobs
        if ($user->user_type !== 'company') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بمراقبة الـ Jobs'
            ], 403);
        }

        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();

            // آخر Jobs ناجحة (من Log - تقدير)
            $recentJobs = DB::table('jobs')
                ->select('queue', DB::raw('COUNT(*) as count'))
                ->groupBy('queue')
                ->get();

            $failedByType = DB::table('failed_jobs')
                ->select(DB::raw("JSON_EXTRACT(payload, '$.displayName') as job_type"), DB::raw('COUNT(*) as count'))
                ->groupBy('job_type')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'pending_jobs' => $pending,
                    'failed_jobs' => $failed,
                    'queues' => $recentJobs,
                    'failed_by_type' => $failedByType,
                    'is_healthy' => $failed < 10,
                    'last_check' => now()->toIso8601String(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('JobsMonitorController::getStats failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في الحصول على الإحصائيات',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * الحصول على قائمة الـ Jobs الفاشلة
     * @OA\Get(
     *     path="/api/jobs/failed",
     *     summary="Get list of failed jobs",
     *     tags={"Jobs Monitor"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Failed jobs retrieved successfully")
     * )
     */
    public function getFailedJobs(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->user_type !== 'company') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بمراقبة الـ Jobs'
            ], 403);
        }

        try {
            $failedJobs = DB::table('failed_jobs')
                ->select('id', 'uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at')
                ->orderBy('failed_at', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($job) {
                    $payload = json_decode($job->payload, true);
                    return [
                        'id' => $job->id,
                        'uuid' => $job->uuid,
                        'job_type' => $payload['displayName'] ?? 'Unknown',
                        'queue' => $job->queue,
                        'failed_at' => $job->failed_at,
                        'exception_summary' => substr($job->exception, 0, 200) . '...',
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $failedJobs,
                'total' => DB::table('failed_jobs')->count()
            ]);
        } catch (\Exception $e) {
            Log::error('JobsMonitorController::getFailedJobs failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في الحصول على الـ Jobs الفاشلة'
            ], 500);
        }
    }

    /**
     * إعادة محاولة Job فاشل
     * @OA\Post(
     *     path="/api/jobs/retry/{uuid}",
     *     summary="Retry a failed job",
     *     tags={"Jobs Monitor"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Job retried successfully")
     * )
     */
    public function retryJob(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        if ($user->user_type !== 'company') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإعادة محاولة الـ Jobs'
            ], 403);
        }

        try {
            Artisan::call('queue:retry', ['id' => [$uuid]]);

            Log::info('Job retried', [
                'uuid' => $uuid,
                'retried_by' => $user->user_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إعادة محاولة الـ Job بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('JobsMonitorController::retryJob failed', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في إعادة محاولة الـ Job'
            ], 500);
        }
    }

    /**
     * حذف جميع الـ Jobs الفاشلة
     * @OA\Delete(
     *     path="/api/jobs/failed",
     *     summary="Clear all failed jobs",
     *     tags={"Jobs Monitor"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Failed jobs cleared successfully")
     * )
     */
    public function clearFailed(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->user_type !== 'company') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بحذف الـ Jobs الفاشلة'
            ], 403);
        }

        try {
            $count = DB::table('failed_jobs')->count();
            Artisan::call('queue:flush');

            Log::info('Failed jobs cleared', [
                'count' => $count,
                'cleared_by' => $user->user_id
            ]);

            return response()->json([
                'success' => true,
                'message' => "تم حذف {$count} Jobs فاشلة"
            ]);
        } catch (\Exception $e) {
            Log::error('JobsMonitorController::clearFailed failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في حذف الـ Jobs الفاشلة'
            ], 500);
        }
    }

    /**
     * إعادة محاولة جميع الـ Jobs الفاشلة
     * @OA\Post(
     *     path="/api/jobs/retry-all",
     *     summary="Retry all failed jobs",
     *     tags={"Jobs Monitor"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="All failed jobs retried")
     * )
     */
    public function retryAll(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->user_type !== 'company') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإعادة محاولة الـ Jobs'
            ], 403);
        }

        try {
            $count = DB::table('failed_jobs')->count();
            Artisan::call('queue:retry', ['id' => ['all']]);

            Log::info('All failed jobs retried', [
                'count' => $count,
                'retried_by' => $user->user_id
            ]);

            return response()->json([
                'success' => true,
                'message' => "تم إعادة محاولة {$count} Jobs"
            ]);
        } catch (\Exception $e) {
            Log::error('JobsMonitorController::retryAll failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل في إعادة محاولة الـ Jobs'
            ], 500);
        }
    }
}
