<?php

namespace App\DTOs\Notification;

use App\Models\NotificationStatus;

class NotificationResponseDTO
{
    public function __construct(
        public readonly int $notificationId,
        public readonly string $moduleOption,
        public readonly string $moduleStatus,
        public readonly string $moduleKeyId,
        public readonly int $staffId,
        public readonly bool $isRead,
        public readonly ?string $staffName = null,
    ) {}

    /**
     * Create DTO from model
     */
    public static function fromModel(NotificationStatus $notification, bool $includeRelations = false): self
    {
        return new self(
            notificationId: $notification->notification_status_id,
            moduleOption: $notification->module_option,
            moduleStatus: $notification->module_status,
            moduleKeyId: $notification->module_key_id,
            staffId: $notification->staff_id,
            isRead: $notification->isRead(),
            staffName: $includeRelations && $notification->relationLoaded('staff')
                ? $notification->staff?->first_name . ' ' . $notification->staff?->last_name
                : null,
        );
    }

    /**
     * Convert DTO to array
     */
    public function toArray(): array
    {
        $data = [
            'notification_id' => $this->notificationId,
            'module_option' => $this->moduleOption,
            'module_status' => $this->moduleStatus,
            'module_key_id' => $this->moduleKeyId,
            'request_id' => $this->moduleKeyId, // alias لتوحيد التسمية مع Push Notification
            'staff_id' => $this->staffId,
            'is_read' => $this->isRead,
        ];

        if ($this->staffName !== null) {
            $data['staff_name'] = $this->staffName;
        }

        return $data;
    }
}
