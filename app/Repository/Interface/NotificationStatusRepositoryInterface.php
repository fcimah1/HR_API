<?php

namespace App\Repository\Interface;

use App\DTOs\Notification\CreateNotificationDTO;
use App\Models\NotificationStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotificationStatusRepositoryInterface
{
    /**
     * Create notifications for multiple staff members
     */
    public function createNotifications(CreateNotificationDTO $dto): int;

    /**
     * Get user notifications with pagination
     */
    public function getUserNotifications(int $userId, ?string $moduleOption = null, int $perPage = 20): LengthAwarePaginator;

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, int $userId): bool;

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(int $userId, ?string $moduleOption = null): int;

    /**
     * Get unread count for user
     */
    public function getUnreadCount(int $userId, ?string $moduleOption = null): int;

    /**
     * Delete notification
     */
    public function deleteNotification(int $notificationId): bool;

    /**
     * Find notification by module and key
     */
    public function findByModuleKey(string $moduleOption, string $moduleKeyId, int $userId): ?NotificationStatus;
}
