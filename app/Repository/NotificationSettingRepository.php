<?php

namespace App\Repository;

use App\Repository\Interface\NotificationSettingRepositoryInterface;
use App\DTOs\Notification\NotificationSettingDTO;
use App\Models\NotificationSetting;
use Illuminate\Support\Facades\Log;

class NotificationSettingRepository implements NotificationSettingRepositoryInterface
{
    /**
     * Get notification settings for a module
     */
    public function getSettingByModule(int $companyId, string $moduleOption): ?NotificationSetting
    {
        return NotificationSetting::byCompany($companyId)
            ->byModule($moduleOption)
            ->first();
    }

    /**
     * Create or update notification settings
     */
    public function createOrUpdateSetting(NotificationSettingDTO $dto): NotificationSetting
    {
        $existing = $this->getSettingByModule($dto->companyId, $dto->moduleOption);

        $data = $dto->toArray();

        if ($existing) {
            $existing->update($data);
            Log::info('Notification settings updated', [
                'notification_id' => $existing->notification_id,
                'module' => $dto->moduleOption,
            ]);
            return $existing->fresh();
        }

        $data['added_at'] = date('Y-m-d H:i:s');
        $setting = NotificationSetting::create($data);

        Log::info('Notification settings created', [
            'notification_id' => $setting->notification_id,
            'module' => $dto->moduleOption,
        ]);

        return $setting;
    }

    /**
     * Get list of users to notify upon submission
     */
    public function getSubmissionNotifiers(int $companyId, string $moduleOption): array
    {
        $setting = $this->getSettingByModule($companyId, $moduleOption);
        return $setting ? $setting->notifyUponSubmissionArray : [];
    }

    /**
     * Get list of users to notify upon approval
     */
    public function getApprovalNotifiers(int $companyId, string $moduleOption): array
    {
        $setting = $this->getSettingByModule($companyId, $moduleOption);
        return $setting ? $setting->notifyUponApprovalArray : [];
    }

    /**
     * Get approver for specific approval level
     */
    public function getApprovalLevelApprover(int $companyId, string $moduleOption, int $level): ?int
    {
        if ($level < 1 || $level > 5) {
            return null;
        }

        $setting = $this->getSettingByModule($companyId, $moduleOption);
        return $setting ? $setting->getApproverForLevel($level) : null;
    }

    /**
     * Check if module has multi-level approval enabled
     */
    public function hasMultiLevelApproval(int $companyId, string $moduleOption): bool
    {
        $setting = $this->getSettingByModule($companyId, $moduleOption);
        return $setting ? $setting->hasMultiLevelApproval() : false;
    }

    /**
     * Get total approval levels for a module
     */
    public function getTotalApprovalLevels(int $companyId, string $moduleOption): int
    {
        $setting = $this->getSettingByModule($companyId, $moduleOption);
        return $setting ? $setting->getTotalApprovalLevels() : 0;
    }
}
