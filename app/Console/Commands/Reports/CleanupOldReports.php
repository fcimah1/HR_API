<?php

namespace App\Console\Commands\Reports;

use App\Models\GeneratedReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupOldReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:cleanup 
                            {--days=7 : Number of days to keep reports}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'حذف التقارير القديمة من Storage وقاعدة البيانات';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');

        $this->info("جاري البحث عن التقارير الأقدم من {$days} أيام...");

        $oldReports = GeneratedReport::where('created_at', '<', now()->subDays($days))
            ->where('status', 'completed')
            ->get();

        if ($oldReports->isEmpty()) {
            $this->info('لا توجد تقارير قديمة للحذف.');
            return Command::SUCCESS;
        }

        $deletedFiles = 0;
        $deletedRecords = 0;

        foreach ($oldReports as $report) {
            try {
                // Delete file if exists
                if ($report->file_path && file_exists($report->getFileFullPath())) {
                    unlink($report->getFileFullPath());
                    $deletedFiles++;
                }

                // Delete database record
                $report->delete();
                $deletedRecords++;
            } catch (\Exception $e) {
                $this->error("فشل حذف التقرير #{$report->report_id}: {$e->getMessage()}");
                Log::error('Failed to delete old report', [
                    'report_id' => $report->report_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("تم الحذف بنجاح:");
        $this->line("  - الملفات: {$deletedFiles}");
        $this->line("  - السجلات: {$deletedRecords}");

        return Command::SUCCESS;
    }
}
