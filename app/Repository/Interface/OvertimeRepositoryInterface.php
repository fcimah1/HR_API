<?php

namespace App\Repository\Interface;

use App\DTOs\Overtime\OvertimeRequestFilterDTO;
use App\Models\OvertimeRequest;
use App\Models\User;

interface OvertimeRepositoryInterface
{
    /**
     * Get paginated overtime requests with filters.
     */
    public function getPaginatedRequests(OvertimeRequestFilterDTO $filters, User $user): array;

    /**
     * Create a new overtime request.
     */
    public function createRequest(array $data): OvertimeRequest;

    /**
     * Update an overtime request.
     */
    public function updateRequest(OvertimeRequest $request, array $data): OvertimeRequest;

    /**
     * Delete an overtime request.
     */
    public function deleteRequest(OvertimeRequest $request): bool;

    /**
     * Find overtime request by ID within company.
     */
    public function findRequestInCompany(int $requestId, int $companyId): ?OvertimeRequest;

    /**
     * Approve an overtime request.
     */
    public function approveRequest(OvertimeRequest $request, int $approvedBy): OvertimeRequest;

    /**
     * Reject an overtime request.
     */
    public function rejectRequest(OvertimeRequest $request, int $rejectedBy, string $reason): OvertimeRequest;

    /**
     * Get overtime requests by manager (subordinates).
     */
    public function getRequestsByManager(int $managerId, int $companyId): \Illuminate\Database\Eloquent\Collection;

    /**
     * Get requests requiring approval from specific user.
     */
    public function getRequestsRequiringApproval(int $userId, int $companyId): \Illuminate\Database\Eloquent\Collection;

    /**
     * Get statistics for company overtime requests.
     */
    public function getStats(int $companyId, ?string $fromDate = null, ?string $toDate = null): array;

    /**
     * Check if user can access overtime request.
     */
    public function canUserAccessRequest(User $user, OvertimeRequest $request): bool;

    /**
     * Check if employee has overlapping overtime request.
     */
    public function hasOverlappingOvertime(int $employeeId, string $requestDate, string $clockIn, string $clockOut, ?int $excludeRequestId = null): bool;
}
