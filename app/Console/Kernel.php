<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // ===== معالجة الـ Queue للـ cPanel =====
        // تشغيل Queue Worker كل دقيقة (للـ Notifications وغيرها)
        $schedule->command('queue:work --stop-when-empty --max-time=55')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground()
            ->description('Process queued jobs (cPanel compatible)');

        // ===== معالجة البصمة =====
        // معالجة سجلات البصمة كل 5 دقائق
        $schedule->command('biometric:process-logs --sync')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->description('Process unprocessed biometric logs');

        // ===== الصيانة اليومية =====
        // تنظيف الـ logs القديمة يومياً في الساعة 2:00 صباحاً
        $schedule->command('logs:clean --days=60')
            ->daily()
            ->at('02:00')
            ->description('Clean log files older than 60 days');

        // ===== الصيانة الأسبوعية =====
        // تنظيف الـ cache أسبوعياً
        $schedule->command('cache:clear')
            ->weekly()
            ->sundays()
            ->at('03:00')
            ->description('Clear application cache weekly');

        // ===== الصيانة الشهرية =====
        // تحسين قاعدة البيانات شهرياً
        $schedule->command('optimize:clear')
            ->monthly()
            ->description('Clear all cached bootstrap files');

        // ===== تنظيف التقارير القديمة =====
        // حذف التقارير التي مر عليها أكثر من 7 أيام يومياً
        $schedule->command('reports:cleanup --days=7')
            ->daily()
            ->at('01:00')
            ->description('Cleanup old generated reports');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
