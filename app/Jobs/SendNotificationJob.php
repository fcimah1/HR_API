<?php

namespace App\Jobs;

use App\DTOs\Notification\CreateNotificationDTO;
use App\Repository\Interface\NotificationStatusRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
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
        public int|string $status,
        public string $moduleKeyId,
        public array $staffIds
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        NotificationStatusRepositoryInterface $statusRepository,
        \App\Services\PushNotificationService $pushService
    ): void {
        try {
            Log::info('SendNotificationJob started', [
                'module' => $this->moduleOption,
                'key_id' => $this->moduleKeyId,
                'staff_ids' => $this->staffIds,
            ]);

            $dto = CreateNotificationDTO::create(
                $this->moduleOption,
                $this->status,
                $this->moduleKeyId,
                $this->staffIds
            );

            $count = $statusRepository->createNotifications($dto);

            // Send Push Notification
            try {
                // Determine submitter name (optional - can be enhanced)
                $submitterName = 'النظام';
                $pushService->sendSubmissionPush($this->staffIds, $this->moduleOption, $submitterName);
            } catch (\Exception $e) {
                Log::error('Push Notification Failed', ['error' => $e->getMessage()]);
            }

            Log::info('SendNotificationJob completed', [
                'module' => $this->moduleOption,
                'key_id' => $this->moduleKeyId,
                'notifications_created' => $count,
            ]);
        } catch (\Exception $e) {
            Log::error('SendNotificationJob failed', [
                'module' => $this->moduleOption,
                'key_id' => $this->moduleKeyId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
