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
        // تنظيف الـ logs القديمة يومياً في الساعة 2:00 صباحاً
        $schedule->command('logs:clean --days=30')
                 ->daily()
                 ->at('02:00')
                 ->description('Clean log files older than 30 days');

        // تنظيف الـ cache أسبوعياً
        $schedule->command('cache:clear')
                 ->weekly()
                 ->sundays()
                 ->at('03:00')
                 ->description('Clear application cache weekly');

        // تحسين قاعدة البيانات شهرياً
        $schedule->command('optimize:clear')
                 ->monthly()
                 ->description('Clear all cached bootstrap files');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
