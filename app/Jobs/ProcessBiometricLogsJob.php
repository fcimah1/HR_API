<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BiometricLog;
use App\Services\AttendanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job لمعالجة سجلات البصمة الخام وتسجيلها في جدول الحضور
 */
class ProcessBiometricLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * عدد المحاولات في حالة الفشل
     */
    public int $tries = 3;

    /**
     * الوقت بالثواني قبل إعادة المحاولة
     */
    public int $backoff = 60;

    /**
     * الحد الأقصى للسجلات المعالجة في كل تشغيل
     */
    private int $batchSize;

    /**
     * معرف الشركة (اختياري - لمعالجة شركة محددة)
     */
    private ?int $companyId;

    public function __construct(?int $companyId = null, int $batchSize = 100)
    {
        $this->companyId = $companyId;
        $this->batchSize = $batchSize;
    }

    /**
     * تنفيذ الـ Job
     */
    public function handle(AttendanceService $attendanceService): void
    {
        Log::info('ProcessBiometricLogsJob started', [
            'company_id' => $this->companyId,
            'batch_size' => $this->batchSize,
        ]);

        $query = BiometricLog::unprocessed()
            ->whereNotNull('user_id') // فقط السجلات المرتبطة بموظفين
            ->orderBy('punch_time', 'asc'); // ترتيب حسب الوقت للمعالجة الصحيحة

        if ($this->companyId) {
            $query->forCompany($this->companyId);
        }

        $logs = $query->limit($this->batchSize)->get();

        if ($logs->isEmpty()) {
            Log::info('ProcessBiometricLogsJob: No unprocessed logs found');
            return;
        }

        $processed = 0;
        $failed = 0;
        $errors = [];

        foreach ($logs as $log) {
            try {
                $this->processLog($log, $attendanceService);
                $processed++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'log_id' => $log->id,
                    'employee_id' => $log->employee_id,
                    'error' => $e->getMessage(),
                ];

                // تحديث السجل بالخطأ
                $log->update([
                    'processing_notes' => $e->getMessage(),
                ]);

                Log::warning('ProcessBiometricLogsJob: Failed to process log', [
                    'log_id' => $log->id,
                    'employee_id' => $log->employee_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('ProcessBiometricLogsJob completed', [
            'processed' => $processed,
            'failed' => $failed,
            'errors' => $errors,
        ]);
    }

    /**
     * معالجة سجل بصمة واحد
     */
    private function processLog(BiometricLog $log, AttendanceService $attendanceService): void
    {
        try {
            $result = $attendanceService->biometricPunch(
                companyId: $log->company_id,
                branchId: $log->branch_id,
                employeeId: $log->employee_id,
                punchTime: $log->punch_time->format('Y-m-d H:i:s'),
                verifyMode: $log->verify_mode,
                punchType: $log->punch_type,
                workCode: null
            );

            // تحديث السجل كمعالج
            $log->markAsProcessed(
                attendanceId: $result['data']['attendance_id'] ?? null,
                notes: $result['message'] ?? 'تمت المعالجة بنجاح'
            );

            Log::debug('Biometric log processed successfully', [
                'log_id' => $log->id,
                'type' => $result['type'] ?? 'unknown',
                'attendance_id' => $result['data']['attendance_id'] ?? null,
            ]);
        } catch (\Exception $e) {
            // تسجيل الخطأ بدون إيقاف المعالجة
            $log->update([
                'is_processed' => true, // نحدده كمعالج حتى لا يُعاد معالجته
                'processed_at' => now(),
                'processing_notes' => 'فشل: ' . $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * التعامل مع فشل الـ Job
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessBiometricLogsJob failed completely', [
            'company_id' => $this->companyId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
