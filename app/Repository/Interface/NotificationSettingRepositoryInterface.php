<?php

namespace App\Repository\Interface;

use App\DTOs\Notification\NotificationSettingDTO;
use App\Models\NotificationSetting;

interface NotificationSettingRepositoryInterface
{
    /**
     * Get notification settings for a module
     */
    public function getSettingByModule(int $companyId, string $moduleOption): ?NotificationSetting;

    /**
     * Create or update notification settings
     */
    public function createOrUpdateSetting(NotificationSettingDTO $dto): NotificationSetting;

    /**
     * Get list of users to notify upon submission
     */
    public function getSubmissionNotifiers(int $companyId, string $moduleOption): array;

    /**
     * Get list of users to notify upon approval
     */
    public function getApprovalNotifiers(int $companyId, string $moduleOption): array;

    /**
     * Get approver for specific approval level
     */
    public function getApprovalLevelApprover(int $companyId, string $moduleOption, int $level): ?int;

    /**
     * Check if module has multi-level approval enabled
     */
    public function hasMultiLevelApproval(int $companyId, string $moduleOption): bool;

    /**
     * Get total approval levels for a module
     */
    public function getTotalApprovalLevels(int $companyId, string $moduleOption): int;
}
