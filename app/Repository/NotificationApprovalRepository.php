<?php

namespace App\Repository;

use App\Repository\Interface\NotificationApprovalRepositoryInterface;
use App\DTOs\Notification\ApprovalActionDTO;
use App\Models\NotificationApproval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class NotificationApprovalRepository implements NotificationApprovalRepositoryInterface
{
    /**
     * Create approval record
     */
    public function createApproval(ApprovalActionDTO $dto): NotificationApproval
    {
        $approval = NotificationApproval::create($dto->toArray());

        Log::info('Approval recorded', [
            'approval_id' => $approval->staff_approval_id,
            'module' => $dto->moduleOption,
            'key_id' => $dto->moduleKeyId,
            'status' => $dto->status,
        ]);

        return $approval;
    }

    /**
     * Get all approvals for a request
     */
    public function getRequestApprovals(string $moduleOption, string $moduleKeyId): Collection
    {
        return NotificationApproval::byRequest($moduleOption, $moduleKeyId)
            ->with('staff')
            ->orderBy('staff_approval_id', 'asc')
            ->get();
    }

    /**
     * Get current approval level for a request
     */
    public function getCurrentApprovalLevel(string $moduleOption, string $moduleKeyId): int
    {
        $approvals = $this->getRequestApprovals($moduleOption, $moduleKeyId);

        if ($approvals->isEmpty()) {
            return 0;
        }

        // Count approved levels only
        return $approvals->where('status', NotificationApproval::STATUS_APPROVED)->count();
    }

    /**
     * Check if user has already approved this request
     */
    public function hasUserApproved(int $userId, string $moduleOption, string $moduleKeyId): bool
    {
        return NotificationApproval::byRequest($moduleOption, $moduleKeyId)
            ->where('staff_id', $userId)
            ->exists();
    }

    /**
     * Get pending approvals for user
     */
    public function getPendingApprovals(int $userId, int $companyId, ?string $moduleOption = null): Collection
    {
        $query = NotificationApproval::byCompany($companyId)
            ->where('staff_id', $userId)
            ->pending()
            ->with('staff');

        if ($moduleOption) {
            $query->byModule($moduleOption);
        }

        return $query->get();
    }

    /**
     * Get approval status for a request
     */
    public function getApprovalStatus(string $moduleOption, string $moduleKeyId): ?string
    {
        $approvals = $this->getRequestApprovals($moduleOption, $moduleKeyId);

        if ($approvals->isEmpty()) {
            return 'pending';
        }

        // Check if any rejection exists
        if ($approvals->where('status', NotificationApproval::STATUS_REJECTED)->isNotEmpty()) {
            return 'rejected';
        }

        // Check if all are approved
        if ($approvals->where('status', NotificationApproval::STATUS_APPROVED)->count() === $approvals->count()) {
            return 'approved';
        }

        return 'in_progress';
    }
}
