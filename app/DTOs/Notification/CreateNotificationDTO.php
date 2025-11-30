<?php

namespace App\DTOs\Notification;

class CreateNotificationDTO
{
    public function __construct(
        public readonly string $moduleOption,
        public readonly string $moduleStatus,
        public readonly string $moduleKeyId,
        public readonly array $staffIds,
    ) {}

    /**
     * Create from parameters
     */
    public static function create(
        string $moduleOption,
        string $moduleStatus,
        string $moduleKeyId,
        array $staffIds
    ): self {
        return new self(
            moduleOption: $moduleOption,
            moduleStatus: $moduleStatus,
            moduleKeyId: $moduleKeyId,
            staffIds: array_filter($staffIds), // Remove empty values
        );
    }

    /**
     * Convert to array for database insertion
     */
    public function toArray(): array
    {
        return [
            'module_option' => $this->moduleOption,
            'module_status' => $this->moduleStatus,
            'module_key_id' => $this->moduleKeyId,
        ];
    }
}
