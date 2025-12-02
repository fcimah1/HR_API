<?php

namespace App\Services;

use App\DTOs\Notification\ApprovalActionDTO;
use App\Repository\Interface\NotificationSettingRepositoryInterface;
use App\Repository\Interface\NotificationApprovalRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ApprovalWorkflowService
{
    public function __construct(
        protected NotificationSettingRepositoryInterface $settingRepository,
        protected NotificationApprovalRepositoryInterface $approvalRepository,
        protected NotificationService $notificationService,
    ) {}

    /**
     * Submit request for approval
     */
    public function submitForApproval(
        string $moduleOption,
        string $moduleKeyId,
        int $submitterId,
        int $companyId
    ): bool {
        // Check if multi-level approval is enabled
        if (!$this->settingRepository->hasMultiLevelApproval($companyId, $moduleOption)) {
            Log::info('Multi-level approval not enabled', [
                'module' => $moduleOption,
                'company_id' => $companyId,
            ]);
            return false;
        }

        // Get first level approver
        $firstApprover = $this->settingRepository->getApprovalLevelApprover($companyId, $moduleOption, 1);

        if (!$firstApprover) {
            Log::warning('No first level approver configured', [
                'module' => $moduleOption,
                'company_id' => $companyId,
            ]);
            return false;
        }

        // Send notification to first approver
        $this->notificationService->sendCustomNotification(
            $moduleOption,
            $moduleKeyId,
            [$firstApprover],
            'pending'
        );

        Log::info('Request pending for approval', [
            'module' => $moduleOption,
            'key_id' => $moduleKeyId,
            'submitter' => $submitterId,
            'first_approver' => $firstApprover,
        ]);

        return true;
    }

    /**
     * Process approval/rejection
     */
    public function processApproval(ApprovalActionDTO $dto): array
    {
        // Check if user already approved
        if ($this->approvalRepository->hasUserApproved($dto->staffId, $dto->moduleOption, $dto->moduleKeyId)) {
            throw new \Exception('لقد قمت بالموافقة على هذا الطلب مسبقاً');
        }

        // Get current level
        $currentLevel = $this->approvalRepository->getCurrentApprovalLevel($dto->moduleOption, $dto->moduleKeyId);
        $newLevel = $currentLevel + 1;

        // Create approval with correct level
        $approvalDto = new ApprovalActionDTO(
            moduleOption: $dto->moduleOption,
            moduleKeyId: $dto->moduleKeyId,
            status: $dto->status,
            staffId: $dto->staffId,
            companyId: $dto->companyId,
            approvalLevel: $newLevel
        );

        $approval = $this->approvalRepository->createApproval($approvalDto);

        // If rejected, notify submitter and stop
        if ($dto->isRejection()) {
            // Send rejection notification
            $this->notificationService->sendApprovalNotification(
                $dto->moduleOption,
                $dto->moduleKeyId,
                $dto->companyId,
                3, // REJECTED
                $dto->staffId,
                $newLevel
            );

            return [
                'status' => 'rejected',
                'message' => 'تم رفض الطلب',
                'approval_id' => $approval->staff_approval_id,
            ];
        }

        // If approved, check if more levels needed
        $totalLevels = $this->settingRepository->getTotalApprovalLevels($dto->companyId, $dto->moduleOption);

        if ($newLevel >= $totalLevels) {
            // Fully approved
            $this->notificationService->sendApprovalNotification(
                $dto->moduleOption,
                $dto->moduleKeyId,
                $dto->companyId,
                2, // APPROVED
                $dto->staffId,
                $newLevel
            );

            return [
                'status' => 'fully_approved',
                'message' => 'تمت الموافقة النهائية على الطلب',
                'approval_id' => $approval->staff_approval_id,
            ];
        }

        // Get next approver
        $nextLevel = $newLevel + 1;
        $nextApprover = $this->settingRepository->getApprovalLevelApprover($dto->companyId, $dto->moduleOption, $nextLevel);

        if ($nextApprover) {
            $this->notificationService->sendCustomNotification(
                $dto->moduleOption,
                $dto->moduleKeyId,
                [$nextApprover],
                1 // PENDING
            );
        }

        return [
            'status' => 'approved_next_level',
            'message' => 'تمت الموافقة، في انتظار موافقة المستوى التالي',
            'approval_id' => $approval->staff_approval_id,
            'current_level' => $newLevel,
            'total_levels' => $totalLevels,
        ];
    }

    /**
     * Get current approval level for a request
     */
    public function getCurrentApprovalLevel(string $moduleOption, string $moduleKeyId): int
    {
        return $this->approvalRepository->getCurrentApprovalLevel($moduleOption, $moduleKeyId);
    }

    /**
     * Check if request is fully approved
     */
    public function isFullyApproved(string $moduleOption, string $moduleKeyId, int $companyId): bool
    {
        $currentLevel = $this->getCurrentApprovalLevel($moduleOption, $moduleKeyId);
        $totalLevels = $this->settingRepository->getTotalApprovalLevels($companyId, $moduleOption);

        return $currentLevel >= $totalLevels && $totalLevels > 0;
    }

    /**
     * Check if user can approve this request
     */
    public function canUserApprove(int $userId, string $moduleOption, string $moduleKeyId, int $companyId): bool
    {
        // Check if already approved
        if ($this->approvalRepository->hasUserApproved($userId, $moduleOption, $moduleKeyId)) {
            return false;
        }

        // Get current level and next expected approver
        $currentLevel = $this->getCurrentApprovalLevel($moduleOption, $moduleKeyId);
        $nextLevel = $currentLevel + 1;
        $expectedApprover = $this->settingRepository->getApprovalLevelApprover($companyId, $moduleOption, $nextLevel);

        return $expectedApprover === $userId;
    }

    /**
     * Get pending approvals for user
     */
    public function getPendingApprovalsForUser(int $userId, int $companyId, ?string $moduleOption = null): array
    {
        $approvals = $this->approvalRepository->getPendingApprovals($userId, $companyId, $moduleOption);

        return $approvals->map(function ($approval) {
            return [
                'approval_id' => $approval->staff_approval_id,
                'module_option' => $approval->module_option,
                'module_key_id' => $approval->module_key_id,
                'status' => $approval->status,
                'approval_level' => $approval->approval_level,
            ];
        })->toArray();
    }

    /**
     * Get approval history for a request
     */
    public function getApprovalHistory(string $moduleOption, string $moduleKeyId): array
    {
        $approvals = $this->approvalRepository->getRequestApprovals($moduleOption, $moduleKeyId);

        return $approvals->map(function ($approval) {
            return [
                'approval_id' => $approval->staff_approval_id,
                'staff_id' => $approval->staff_id,
                'staff_name' => $approval->staff?->first_name . ' ' . $approval->staff?->last_name,
                'status' => $approval->status,
                'status_text' => $approval->isApproved() ? 'موافق' : ($approval->isRejected() ? 'مرفوض' : 'معلق'),
                'approval_level' => $approval->approval_level,
                'updated_at' => $approval->updated_at,
            ];
        })->toArray();
    }
}
