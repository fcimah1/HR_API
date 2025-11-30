<?php

namespace App\Repository;

use App\Repository\Interface\NotificationStatusRepositoryInterface;
use App\DTOs\Notification\CreateNotificationDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use App\Models\NotificationStatus;

class NotificationStatusRepository implements NotificationStatusRepositoryInterface
{
    /**
     * Create notifications for multiple staff members
     */
    public function createNotifications(CreateNotificationDTO $dto): int
    {
        $baseData = $dto->toArray();
        $created = 0;

        foreach ($dto->staffIds as $staffId) {
            $data = array_merge($baseData, [
                'staff_id' => $staffId,
                'is_read' => 0,
            ]);

            NotificationStatus::create($data);
            $created++;
        }

        Log::info('Notifications created', [
            'module' => $dto->moduleOption,
            'key_id' => $dto->moduleKeyId,
            'staff_count' => $created,
        ]);

        return $created;
    }

    /**
     * Get user notifications with pagination
     */
    public function getUserNotifications(int $userId, ?string $moduleOption = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = NotificationStatus::byStaff($userId)
            ->with('staff')
            ->orderBy('notification_status_id', 'desc');

        if ($moduleOption) {
            $query->byModule($moduleOption);
        }

        return $query->paginate($perPage);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = NotificationStatus::byStaff($userId)
            ->find($notificationId);

        if (!$notification) {
            return false;
        }

        return $notification->markAsRead();
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(int $userId, ?string $moduleOption = null): int
    {
        $query = NotificationStatus::byStaff($userId)->unread();

        if ($moduleOption) {
            $query->byModule($moduleOption);
        }

        return $query->update(['is_read' => 1]);
    }

    /**
     * Get unread count for user
     */
    public function getUnreadCount(int $userId, ?string $moduleOption = null): int
    {
        $query = NotificationStatus::byStaff($userId)->unread();

        if ($moduleOption) {
            $query->byModule($moduleOption);
        }

        return $query->count();
    }

    /**
     * Delete notification
     */
    public function deleteNotification(int $notificationId): bool
    {
        $notification = NotificationStatus::find($notificationId);

        if (!$notification) {
            return false;
        }

        return $notification->delete();
    }

    /**
     * Find notification by module and key
     */
    public function findByModuleKey(string $moduleOption, string $moduleKeyId, int $userId): ?NotificationStatus
    {
        return NotificationStatus::byModuleKey($moduleOption, $moduleKeyId)
            ->byStaff($userId)
            ->first();
    }
}
