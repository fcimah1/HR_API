<?php

namespace App\Services;

use App\DTOs\Notification\CreateNotificationDTO;
use App\DTOs\Notification\NotificationSettingDTO;
use App\DTOs\Notification\NotificationResponseDTO;
use App\DTOs\Notification\ApprovalActionDTO;
use App\Jobs\SendApprovalNotificationJob;
use App\Jobs\SendNotificationJob;
use App\Repository\Interface\NotificationSettingRepositoryInterface;
use App\Repository\Interface\NotificationStatusRepositoryInterface;
use App\Repository\Interface\NotificationApprovalRepositoryInterface;
use App\Enums\NumericalStatusEnum;
use App\Models\UserDetails;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(
        protected NotificationSettingRepositoryInterface $settingRepository,
        protected NotificationStatusRepositoryInterface $statusRepository,
        protected NotificationApprovalRepositoryInterface $approvalRepository,
    ) {}

    /**
     * Send notification upon submission
     */
    public function sendSubmissionNotification(string $moduleOption, string $moduleKeyId, int $companyId, int|string $status = NumericalStatusEnum::PENDING->value, ?int $submitterId = null): int
    {
        $notifiers = $this->settingRepository->getSubmissionNotifiers($companyId, $moduleOption);
        Log::info('Submission notifiers', [
            'module' => $moduleOption,
            'company_id' => $companyId,
            'notifiers' => $notifiers,
        ]);

        // Resolve notifiers (handle 'self', 'manager')
        $resolvedNotifiers = $this->resolveNotifiers($notifiers, $submitterId);

        if (empty($resolvedNotifiers)) {
            if (empty($notifiers)) {
                Log::info('No submission notifiers configured', [
                    'module' => $moduleOption,
                    'company_id' => $companyId,
                ]);
            }
            return 0;
        }

        // Dispatch job to send notifications asynchronously
        SendNotificationJob::dispatch(
            $moduleOption,
            $status,
            $moduleKeyId,
            $resolvedNotifiers
        );

        return count($resolvedNotifiers);
    }

    /**
     * Send notification upon approval
     */
    public function sendApprovalNotification(
        string $moduleOption,
        string $moduleKeyId,
        int $companyId,
        int|string $status = NumericalStatusEnum::APPROVED->value,
        ?int $approverId = null,
        ?int $approvalLevel = null,
        ?int $submitterId = null
    ): int {
        $notifiers = $this->settingRepository->getApprovalNotifiers($companyId, $moduleOption);

        // Resolve notifiers (handle 'self', 'manager')
        $resolvedNotifiers = $this->resolveNotifiers($notifiers, $submitterId);

        // Always notify the submitter (employee) of the decision
        if ($submitterId) {
            $resolvedNotifiers[] = $submitterId;
        }

        $resolvedNotifiers = array_unique($resolvedNotifiers);

        if (empty($resolvedNotifiers)) {
            Log::info('No notifiers found for approval', [
                'module' => $moduleOption,
                'company_id' => $companyId,
            ]);
            return 0;
        }

        // If approval level is not specified, try to determine it from settings
        $approvalLevel = null;
        if ($approverId !== null) {
            $setting = $this->settingRepository->getSettingByModule($companyId, $moduleOption);
            if ($setting) {
                // Check levels 1 to 5
                for ($i = 1; $i <= 5; $i++) {
                    $levelApprover = $setting->getApproverForLevel($i);
                    if ($levelApprover === $approverId) {
                        $approvalLevel = $i;
                        break;
                    }
                }
            }
        }

        // Dispatch job to handle approval notification asynchronously
        SendApprovalNotificationJob::dispatch(
            $moduleOption,
            $moduleKeyId,
            $companyId,
            $status,
            $approverId,
            $approvalLevel,
            $submitterId,
            $resolvedNotifiers,
        );

        return count($resolvedNotifiers);
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

    /**
     * Resolve notifiers (handle keywords like 'self', 'manager')
     */
    private function resolveNotifiers(array $notifiers, ?int $submitterId): array
    {
        $resolvedIds = [];

        foreach ($notifiers as $notifier) {
            if (is_numeric($notifier)) {
                $resolvedIds[] = (int)$notifier;
                continue;
            }

            if (!$submitterId) {
                Log::debug('resolveNotifiers: No submitterId provided', ['notifier' => $notifier]);
                continue;
            }

            if ($notifier === 'self') {
                $resolvedIds[] = $submitterId;
                Log::debug('resolveNotifiers: Added self', ['submitterId' => $submitterId]);
            } elseif ($notifier === 'manager') {
                $details = UserDetails::where('user_id', $submitterId)->first();
                if ($details && $details->reporting_manager) {
                    $resolvedIds[] = $details->reporting_manager;
                    Log::debug('resolveNotifiers: Added manager', [
                        'submitterId' => $submitterId,
                        'managerId' => $details->reporting_manager
                    ]);
                } else {
                    Log::debug('resolveNotifiers: No reporting_manager found', [
                        'submitterId' => $submitterId,
                        'hasDetails' => $details !== null
                    ]);
                }
            }
        }

        Log::info('resolveNotifiers: Final resolved IDs', ['resolvedIds' => array_unique($resolvedIds)]);
        return array_unique($resolvedIds);
    }

    /**
     * Send custom notification to specific users
     */
    public function sendCustomNotification(string $moduleOption, string $moduleKeyId, array $staffIds, int|string $status = NumericalStatusEnum::PENDING->value): int
    {
        if (empty($staffIds)) {
            return 0;
        }

        // Dispatch job to send custom notifications asynchronously
        SendNotificationJob::dispatch(
            $moduleOption,
            $status,
            $moduleKeyId,
            $staffIds
        );

        return count($staffIds);
    }

    /**
     * Get user notifications with pagination
     */
    public function getUserNotifications(int $userId, ?string $moduleOption = null, int $perPage = 20): array
    {
        $notifications = $this->statusRepository->getUserNotifications($userId, $moduleOption, $perPage);

        // Transform notifications to include policy result for travel
        $transformedData = collect($notifications->items())->map(function ($notification) {
            $data = $notification->toArray();

            // إضافة request_id لتوحيد التسمية مع Push Notification
            $data['request_id'] = $notification->module_key_id;

            // Add travel allowance info for travel notifications
            if ($notification->module_option === 'travel_settings') {
                $policyResult = $notification->policy_result; // Uses the accessor
                $data['travel_allowance'] = $policyResult ? [
                    'total_amount' => $policyResult->total_amount,
                    'currency' => $policyResult->currency_local,
                    'daily_rate' => $policyResult->daily_rate,
                    'total_days' => $policyResult->total_days,
                ] : [
                    'message' => 'لم يحدد بعد'
                ];
            }

            return $data;
        })->toArray();

        return [
            'data' => $transformedData,
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
