<?php

namespace App\Jobs;

use App\DTOs\Notification\ApprovalActionDTO;
use App\DTOs\Notification\CreateNotificationDTO;
use App\Enums\NumericalStatusEnum;
use App\Repository\Interface\NotificationApprovalRepositoryInterface;
use App\Repository\Interface\NotificationStatusRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendApprovalNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $moduleOption,
        public string $moduleKeyId,
        public int $companyId,
        public int|string $status,
        public ?int $approverId,
        public ?int $approvalLevel,
        public ?int $submitterId,
        public array $resolvedNotifiers,
        public ?string $additionalData = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        NotificationApprovalRepositoryInterface $approvalRepository,
        NotificationStatusRepositoryInterface $statusRepository,
        \App\Services\PushNotificationService $pushService
    ): void {
        try {
            Log::info('SendApprovalNotificationJob started', [
                'module' => $this->moduleOption,
                'key_id' => $this->moduleKeyId,
                'status' => $this->status,
            ]);

            // Note: Approval recording is now done in the Service layer (e.g., LeaveAdjustmentService)
            // This job only handles notifications to avoid duplicate approval records

            // Send notifications if there are recipients
            if (!empty($this->resolvedNotifiers)) {
                $dto = CreateNotificationDTO::create(
                    $this->moduleOption,
                    $this->status,
                    $this->moduleKeyId,
                    $this->resolvedNotifiers,
                    $this->additionalData
                );

                $count = $statusRepository->createNotifications($dto);

                // Send Push Notification
                try {
                    $statusStr = is_string($this->status) ? $this->status : ($this->status == NumericalStatusEnum::APPROVED->value ? 'approved' : 'rejected');

                    $pushService->sendApprovalPush($this->resolvedNotifiers, $this->moduleOption, $statusStr, $this->moduleKeyId);
                } catch (\Exception $e) {
                    Log::error('Push Notification Failed', ['error' => $e->getMessage()]);
                }

                Log::info('SendApprovalNotificationJob completed', [
                    'module' => $this->moduleOption,
                    'key_id' => $this->moduleKeyId,
                    'notifications_sent' => $count,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('SendApprovalNotificationJob failed', [
                'module' => $this->moduleOption,
                'key_id' => $this->moduleKeyId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Convert string status to integer
     */
    private function convertStatusToInt(string $status): int
    {
        return match ($status) {
            'pending' => NumericalStatusEnum::PENDING->value,
            'approved' => NumericalStatusEnum::APPROVED->value,
            'rejected' => NumericalStatusEnum::REJECTED->value,
            default => NumericalStatusEnum::PENDING->value,
        };
    }
}
