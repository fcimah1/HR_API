<?php

namespace App\Services;

use App\DTOs\Notification\CreateNotificationDTO;
use App\DTOs\Notification\NotificationSettingDTO;
use App\DTOs\Notification\NotificationResponseDTO;
use App\Repository\Interface\NotificationSettingRepositoryInterface;
use App\Repository\Interface\NotificationStatusRepositoryInterface;
use App\Enums\NumericalStatusEnum;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(
        protected NotificationSettingRepositoryInterface $settingRepository,
        protected NotificationStatusRepositoryInterface $statusRepository,
    ) {}

    /**
     * Send notification upon submission
     */
    public function sendSubmissionNotification(
        string $moduleOption,
        string $moduleKeyId,
        int $companyId,
        int|string $status = NumericalStatusEnum::PENDING->value
    ): int {
        $notifiers = $this->settingRepository->getSubmissionNotifiers($companyId, $moduleOption);

        if (empty($notifiers)) {
            Log::info('No submission notifiers configured', [
                'module' => $moduleOption,
                'company_id' => $companyId,
            ]);
            return 0;
        }

        $dto = CreateNotificationDTO::create(
            $moduleOption,
            $status,
            $moduleKeyId,
            $notifiers
        );

        return $this->statusRepository->createNotifications($dto);
    }

    /**
     * Send notification upon approval
     */
    public function sendApprovalNotification(
        string $moduleOption,
        string $moduleKeyId,
        int $companyId,
        int|string $status = NumericalStatusEnum::APPROVED->value
    ): int {
        $notifiers = $this->settingRepository->getApprovalNotifiers($companyId, $moduleOption);

        if (empty($notifiers)) {
            Log::info('No approval notifiers configured', [
                'module' => $moduleOption,
                'company_id' => $companyId,
            ]);
            return 0;
        }

        $dto = CreateNotificationDTO::create(
            $moduleOption,
            $status,
            $moduleKeyId,
            $notifiers
        );

        return $this->statusRepository->createNotifications($dto);
    }

    /**
     * Send custom notification to specific users
     */
    public function sendCustomNotification(
        string $moduleOption,
        string $moduleKeyId,
        array $staffIds,
        int|string $status = NumericalStatusEnum::PENDING->value
    ): int {
        if (empty($staffIds)) {
            return 0;
        }

        $dto = CreateNotificationDTO::create(
            $moduleOption,
            $status,
            $moduleKeyId,
            $staffIds
        );

        return $this->statusRepository->createNotifications($dto);
    }

    /**
     * Get user notifications with pagination
     */
    public function getUserNotifications(int $userId, ?string $moduleOption = null, int $perPage = 20): array
    {
        $notifications = $this->statusRepository->getUserNotifications($userId, $moduleOption, $perPage);

        return [
            'data' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'from' => $notifications->firstItem(),
                'to' => $notifications->lastItem(),
                'has_more_pages' => $notifications->hasMorePages(),
            ]
        ];
    }

    /**
     * Mark notification as read
     */
    public function markNotificationAsRead(int $notificationId, int $userId): bool
    {
        return $this->statusRepository->markAsRead($notificationId, $userId);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(int $userId, ?string $moduleOption = null): int
    {
        return $this->statusRepository->markAllAsRead($userId, $moduleOption);
    }

    /**
     * Get unread count
     */
    public function getUnreadCount(int $userId, ?string $moduleOption = null): int
    {
        return $this->statusRepository->getUnreadCount($userId, $moduleOption);
    }

    /**
     * Get notification settings for a module
     */
    public function getNotificationSettings(int $companyId, string $moduleOption): ?array
    {
        $setting = $this->settingRepository->getSettingByModule($companyId, $moduleOption);

        if (!$setting) {
            return null;
        }

        return [
            'notification_id' => $setting->notification_id,
            'company_id' => $setting->company_id,
            'module_options' => $setting->module_options,
            'notify_upon_submission' => $setting->notifyUponSubmissionArray,
            'notify_upon_approval' => $setting->notifyUponApprovalArray,
            'approval_method' => $setting->approval_method,
            'approval_level' => $setting->approval_level,
            'approval_level01' => $setting->approval_level01,
            'approval_level02' => $setting->approval_level02,
            'approval_level03' => $setting->approval_level03,
            'approval_level04' => $setting->approval_level04,
            'approval_level05' => $setting->approval_level05,
            'skip_specific_approval' => $setting->skip_specific_approval,
        ];
    }

    /**
     * Update notification settings
     */
    public function updateNotificationSettings(NotificationSettingDTO $dto): array
    {
        $setting = $this->settingRepository->createOrUpdateSetting($dto);

        return [
            'notification_id' => $setting->notification_id,
            'module_options' => $setting->module_options,
            'message' => 'تم تحديث إعدادات الإشعارات بنجاح',
        ];
    }

    /**
     * Check if multi-level approval is enabled for module
     */
    public function hasMultiLevelApproval(int $companyId, string $moduleOption): bool
    {
        return $this->settingRepository->hasMultiLevelApproval($companyId, $moduleOption);
    }
}
