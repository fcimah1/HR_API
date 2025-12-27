<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Listener للتعامل مع فشل الـ Jobs
 * Handles job failures and sends alerts
 */
class JobFailedListener
{
    /**
     * Handle the event.
     */
    public function handle(JobFailed $event): void
    {
        $jobName = $event->job->resolveName();
        $exception = $event->exception;
        $connectionName = $event->connectionName;
        $queue = $event->job->getQueue();

        // تسجيل الخطأ في Log
        Log::error('🚨 Job Failed Alert', [
            'job' => $jobName,
            'connection' => $connectionName,
            'queue' => $queue,
            'exception' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => substr($exception->getTraceAsString(), 0, 1000),
        ]);

        // محاولة إرسال إيميل للـ Admin (إذا كان الـ MAIL معد)
        $this->notifyAdmin($jobName, $exception, $queue);
    }

    /**
     * إرسال تنبيه للـ Admin
     */
    private function notifyAdmin(string $jobName, \Throwable $exception, ?string $queue): void
    {
        try {
            // التحقق من وجود إيميل Admin
            $adminEmail = config('mail.admin_email') ?? config('mail.from.address');

            if (!$adminEmail || config('mail.default') === 'log') {
                // لا يوجد إيميل معد - فقط نسجل
                Log::warning('Job failure notification skipped - no admin email configured');
                return;
            }

            // إرسال إيميل بسيط
            Mail::raw(
                $this->buildEmailContent($jobName, $exception, $queue),
                function ($message) use ($adminEmail, $jobName) {
                    $message->to($adminEmail)
                        ->subject("🚨 Job Failed: {$jobName}");
                }
            );

            Log::info('Job failure notification sent', [
                'job' => $jobName,
                'admin_email' => $adminEmail
            ]);
        } catch (\Exception $e) {
            // فشل إرسال الإيميل - نسجل فقط
            Log::error('Failed to send job failure notification email', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * بناء محتوى الإيميل
     */
    private function buildEmailContent(string $jobName, \Throwable $exception, ?string $queue): string
    {
        return <<<EMAIL
🚨 Job Failed Alert
=====================

Job: {$jobName}
Queue: {$queue}
Time: {now()->format('Y-m-d H:i:s')}

Error Message:
{$exception->getMessage()}

File: {$exception->getFile()}
Line: {$exception->getLine()}

Stack Trace (partial):
{substr($exception->getTraceAsString(), 0, 500)}

---
HR System - Automated Alert
EMAIL;
    }
}
