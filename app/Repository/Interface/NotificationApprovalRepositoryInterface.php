<?php

namespace App\Repository\Interface;

use App\DTOs\Notification\ApprovalActionDTO;
use App\Models\NotificationApproval;
use Illuminate\Support\Collection;

interface NotificationApprovalRepositoryInterface
{
    /**
     * Create approval record
     */
    public function createApproval(ApprovalActionDTO $dto): NotificationApproval;

    /**
     * Get all approvals for a request
     */
    public function getRequestApprovals(string $moduleOption, string $moduleKeyId): Collection;

    /**
     * Get current approval level for a request
     */
    public function getCurrentApprovalLevel(string $moduleOption, string $moduleKeyId): int;

    /**
     * Check if user has already approved this request
     */
    public function hasUserApproved(int $userId, string $moduleOption, string $moduleKeyId): bool;

    /**
     * Get pending approvals for user
     */
    public function getPendingApprovals(int $userId, int $companyId, ?string $moduleOption = null): Collection;

    /**
     * Get approval status for a request
     */
    public function getApprovalStatus(string $moduleOption, string $moduleKeyId): ?string;
}
