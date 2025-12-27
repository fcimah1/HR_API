<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessBiometricLogsJob;
use Illuminate\Console\Command;

/**
 * أمر Artisan لمعالجة سجلات البصمة الخام
 * 
 * Usage:
 *   php artisan biometric:process-logs              # معالجة كل السجلات
 *   php artisan biometric:process-logs --company=24 # معالجة شركة محددة
 *   php artisan biometric:process-logs --batch=200  # تحديد حجم الدفعة
 *   php artisan biometric:process-logs --sync       # تشغيل متزامن (بدون Queue)
 */
class ProcessBiometricLogsCommand extends Command
{
    /**
     * اسم الأمر
     */
    protected $signature = 'biometric:process-logs 
                            {--company= : معرف الشركة للمعالجة (اختياري)}
                            {--batch=100 : عدد السجلات في كل دفعة}
                            {--sync : تشغيل متزامن بدلاً من Queue}';

    /**
     * وصف الأمر
     */
    protected $description = 'معالجة سجلات البصمة الخام وتسجيلها في جدول الحضور';

    /**
     * تنفيذ الأمر
     */
    public function handle(): int
    {
        $companyId = $this->option('company') ? (int) $this->option('company') : null;
        $batchSize = (int) $this->option('batch');
        $sync = $this->option('sync');

        $this->info('🔄 بدء معالجة سجلات البصمة...');
        $this->info("   الشركة: " . ($companyId ?? 'الكل'));
        $this->info("   حجم الدفعة: {$batchSize}");
        $this->info("   الوضع: " . ($sync ? 'متزامن' : 'Queue'));

        $job = new ProcessBiometricLogsJob($companyId, $batchSize);

        if ($sync) {
            // تشغيل متزامن
            $this->info('⏳ جاري المعالجة...');
            dispatch_sync($job);
            $this->info('✅ اكتملت المعالجة بنجاح');
        } else {
            // إرسال إلى Queue
            dispatch($job);
            $this->info('📤 تم إرسال المهمة إلى Queue');
            $this->info('   استخدم: php artisan queue:work لمعالجة القائمة');
        }

        return Command::SUCCESS;
    }
}
