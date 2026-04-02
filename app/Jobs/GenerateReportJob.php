<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\GeneratedReport;
use App\Models\User;
use App\Services\DownloadPdfService;
use App\Services\ReportExportService;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutes max

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $reportId ,
        public string $reportType,
        public int $userId,
        public int $companyId,
        public array $filters,
        public string $reportTitle,
        public string $dateRange,
        public string $statusText,
        public string $transferTypeText,
        public string $transferType
    ) {
        $this->onQueue('reports');
    }

    /**
     * Execute the job.
     */
    public function handle(
        DownloadPdfService $downloadPdfService,
        PushNotificationService $pushService,
        \App\Repository\Interface\ReportRepositoryInterface $reportRepository
    ): void {
        $report = GeneratedReport::find($this->reportId);

        if (!$report) {
            Log::error('GeneratedReport not found', ['report_id' => $this->reportId]);
            return;
        }

        try {
            // Mark as processing
            $report->markAsProcessing();

            // Get the user
            $user = User::find($this->userId);
            if (!$user) {
                throw new \Exception('User not found: ' . $this->userId);
            }

            // Generate PDF path (Temp)
            $fileName = $this->generateFileName();
            $tempPath = storage_path('app/temp/' . $fileName);

            // Ensure temp directory exists
            if (!is_dir(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            // Generate the report based on type (Using injected Repository)
            $this->generateReport($reportRepository, $downloadPdfService, $user, $tempPath);

            // Move to permanent storage (app/public/reports/...)
            // Note: $storagePath is the relative path stored in DB, e.g. "reports/2026/01/file.pdf"
            $storagePath = 'reports/' . date('Y/m/') . $fileName;

            // Full system path: storage/app/public/reports/...
            $fullStoragePath = storage_path('app/public/' . $storagePath);

            // Ensure directory exists
            $dir = dirname($fullStoragePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Move file
            if (!file_exists($tempPath)) {
                throw new \Exception("Temp file not found at: {$tempPath}");
            }
            if (!rename($tempPath, $fullStoragePath)) {
                throw new \Exception("Failed to move file from {$tempPath} to {$fullStoragePath}");
            }

            // Get file size
            $fileSize = filesize($fullStoragePath);

            // Mark as completed
            $report->markAsCompleted($storagePath, $fileSize);

            // Send success notification
            try {
                $pushService->sendToUser(
                    $this->userId,
                    'التقرير جاهز 📄',
                    "تم تجهيز {$this->reportTitle} بنجاح. اضغط هنا لعرض التقرير.",
                    [
                        'action' => 'download_report',
                        'report_id' => $this->reportId,
                        'report_type' => $this->reportType
                    ]
                );
            } catch (\Exception $notifError) {
                Log::error('Failed to send report success notification', ['error' => $notifError->getMessage()]);
            }

            Log::info('Report generated successfully', [
                'report_id' => $this->reportId,
                'type' => $this->reportType,
                'file_path' => $storagePath,
            ]);
        } catch (\Throwable $e) {
            // Mark as failed
            $report->markAsFailed($e->getMessage());

            Log::error('Report generation failed', [
                'report_id' => $this->reportId,
                'type' => $this->reportType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Send failure notification
            try {
                $pushService->sendToUser(
                    $this->userId,
                    'فشل التقرير ❌',
                    "حدث خطأ أثناء معالجة تقرير {$this->reportTitle}. يرجى المحاولة مرة أخرى."
                );
            } catch (\Exception $notifError) {
                Log::error('Failed to send report failure notification', ['error' => $notifError->getMessage()]);
            }

            throw $e;
        }
    }

    /**
     * Generate the report based on type
     */
    private function generateReport(
        \App\Repository\Interface\ReportRepositoryInterface $reportRepository,
        DownloadPdfService $downloadPdfService,
        User $user,
        string $tempPath
    ): void {
        // 1. Fetch data based on report type
        $data = $this->fetchReportData($reportRepository);

        if ($data->isEmpty()) {
            $report = GeneratedReport::find($this->reportId); // Reload to be safe
            if ($report) {
                $msg = 'لا توجد بيانات للتقرير';
                $report->markAsFailed($msg);

                // Send notification for empty data (optional, maybe distinct from error)
                try {
                    app(PushNotificationService::class)->sendToUser(
                        $this->userId,
                        'عفواً ⚠️',
                        "لا توجد بيانات لعرضها في تقرير {$this->reportTitle}."
                    );
                } catch (\Exception $e) { /* ignore */
                }
            }
            return; // Exit job successfully (no retry needed)
        }

        // 2. Generate and save PDF
        $success = $downloadPdfService->generateAndSavePdf(
            $this->reportType ?? '',
            $data ?? [],
            $this->reportTitle ?? '',
            $this->companyId,
            $this->filters,
            $tempPath,
            $this->dateRange ?? '',
            $user,
            $this->statusText ?? '',
            $this->transferTypeText ?? '',
            $this->transferType ?? '',
        );

        if (!$success) {
            throw new \Exception('فشل توليد ملف PDF');
        }
    }

    /**
     * Fetch report data from repository based on type
     */
    private function fetchReportData(\App\Repository\Interface\ReportRepositoryInterface $repo): Collection
    {
        // Add common filters
        $filters = array_merge($this->filters, [
            'company_id' => $this->companyId
        ]);

        switch ($this->reportType) {
            case 'attendance_monthly':
                $dto = new \App\DTOs\Report\AttendanceReportFilterDTO(
                    companyId: $this->companyId,
                    employeeId: $filters['employee_id'] ?? null,
                    branchId: $filters['branch_id'] ?? null,
                    month: $filters['month'] ?? null,
                    startDate: $filters['start_date'] ?? null,
                    endDate: $filters['end_date'] ?? null,
                    status: $filters['status'] ?? null,
                    employeeIds: $filters['employee_ids'] ?? null
                );
                return $repo->getAttendanceMonthlyReport($dto);

            case 'attendance_first_last':
                $dto = new \App\DTOs\Report\AttendanceReportFilterDTO(
                    companyId: $this->companyId,
                    employeeId: $filters['employee_id'] ?? null,
                    branchId: $filters['branch_id'] ?? null,
                    month: $filters['month'] ?? null,
                    startDate: $filters['start_date'] ?? null,
                    endDate: $filters['end_date'] ?? null,
                    status: $filters['status'] ?? null,
                    employeeIds: $filters['employee_ids'] ?? null
                );
                return $repo->getAttendanceFirstLastReport($dto);

            case 'attendance_time_records':
                $dto = new \App\DTOs\Report\AttendanceReportFilterDTO(
                    companyId: $this->companyId,
                    employeeId: $filters['employee_id'] ?? null,
                    branchId: $filters['branch_id'] ?? null,
                    month: $filters['month'] ?? null,
                    startDate: $filters['start_date'] ?? null,
                    endDate: $filters['end_date'] ?? null,
                    status: $filters['status'] ?? null,
                    employeeIds: $filters['employee_ids'] ?? null
                );
                return $repo->getAttendanceTimeRecordsReport($dto);

            case 'attendance_date_range':
                $dto = new \App\DTOs\Report\AttendanceReportFilterDTO(
                    companyId: $this->companyId,
                    employeeId: $filters['employee_id'] ?? null,
                    branchId: $filters['branch_id'] ?? null,
                    month: $filters['month'] ?? null,
                    startDate: $filters['start_date'] ?? null,
                    endDate: $filters['end_date'] ?? null,
                    status: $filters['status'] ?? null,
                    employeeIds: $filters['employee_ids'] ?? null
                );
                return $repo->getAttendanceDateRangeReport($dto);

            case 'timesheet':
                $dto = new \App\DTOs\Report\AttendanceReportFilterDTO(
                    companyId: $this->companyId,
                    employeeId: $filters['employee_id'] ?? null,
                    branchId: $filters['branch_id'] ?? null,
                    month: $filters['month'] ?? null,
                    startDate: $filters['start_date'] ?? null,
                    endDate: $filters['end_date'] ?? null,
                    status: $filters['status'] ?? null,
                    employeeIds: $filters['employee_ids'] ?? null
                );
                return $repo->getTimesheetReport($dto);

            case 'loan':
                return $repo->getLoanReport($this->companyId, $filters);

            case 'leave':
                return $repo->getLeaveReport($this->companyId, $filters);

            case 'payroll':
                return $repo->getPayrollReport($this->companyId, $filters);

            case 'terminations':
                return $repo->getTerminationsReport($this->companyId, $filters);

            case 'resignations':
                return $repo->getResignationsReport($this->companyId, $filters);

            case 'transfers':
                return $repo->getTransfersReport($this->companyId, $filters);

            case 'residence_renewal':
                return $repo->getResidenceRenewalReport($this->companyId, $filters);

            case 'employees_by_country':
                return $repo->getEmployeesByCountryReport($this->companyId, $filters);

            case 'employees_by_branch':
                return $repo->getEmployeesByBranchReport($this->companyId, $filters);

            case 'awards':
                return $repo->getAwardsReport($this->companyId, $filters);

            case 'promotions':
                return $repo->getPromotionsReport($this->companyId, $filters);

            case 'expiring_contracts':
                return $repo->getExpiringContractsReport($this->companyId, $filters);

            case 'expiring_documents':
                return $repo->getExpiringDocumentsReport($this->companyId, $filters);

            case 'end_of_service':
                return $repo->getEndOfServiceReport($filters);

            default:
                throw new \Exception("Report type '{$this->reportType}' not yet integrated with Queue system");
        }
    }

    /**
     * Generate unique file name
     */
    private function generateFileName(): string
    {
        $sanitizedType = str_replace(['_', '-'], ' ', $this->reportType);
        $sanitizedType = ucwords($sanitizedType);
        $sanitizedType = str_replace(' ', '_', $sanitizedType);

        return sprintf(
            '%s_%s_%s.pdf',
            $sanitizedType,
            $this->userId,
            now()->format('YmdHis')
        );
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $report = GeneratedReport::find($this->reportId);

        if ($report) {
            $report->markAsFailed('Job failed after ' . $this->tries . ' attempts: ' . $exception->getMessage());

            // Send failure notification even on final failure
            try {
                $pushService = app(PushNotificationService::class);
                $pushService->sendToUser(
                    $this->userId,
                    'فشل نهائي للتقرير ❌',
                    "تعذر إنشاء التقرير {$this->reportTitle} بعد عدة محاولات."
                );
            } catch (\Exception $e) {
                // Ignore notification errors in failed handler
            }
        }

        Log::error('GenerateReportJob failed permanently', [
            'report_id' => $this->reportId,
            'error' => $exception->getMessage(),
        ]);
    }
}
