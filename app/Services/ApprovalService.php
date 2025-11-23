<?php

namespace App\Services;

use App\Models\StaffApproval;
use App\Models\UserDetails;
use Illuminate\Support\Facades\Log;

/**
 * Reusable multi-level approval service for all modules.
 * Handles approval workflow tracking in ci_erp_notifications_approval table.
 */
class ApprovalService
{
    /**
     * Get required approval levels for an employee.
     * Returns array of user IDs who need to approve in order.
     */
    public function getRequiredApprovalLevels(int $employeeId): array
    {
        $userDetails = UserDetails::where('user_id', $employeeId)->first();
        
        if (!$userDetails) {
            Log::warning('ApprovalService: UserDetails not found', ['employee_id' => $employeeId]);
            return [];
        }

        // Build approval chain from configured levels
        $approvalChain = array_filter([
            $userDetails->approval_level01,
            $userDetails->approval_level02,
            $userDetails->approval_level03,
        ]);

        // If no approval levels configured, use reporting manager
        if (empty($approvalChain) && $userDetails->reporting_manager) {
            $approvalChain = [$userDetails->reporting_manager];
        }

        Log::info('ApprovalService: Approval chain', [
            'employee_id' => $employeeId,
            'approval_chain' => $approvalChain
        ]);

        return array_values($approvalChain);
    }

    /**
     * Get current approval level for a request.
     * Returns count of approvals already made.
     */
    public function getCurrentApprovalLevel(int $requestId, string $moduleOption): int
    {
        $count = StaffApproval::forOvertime()
            ->forRequest($requestId)
            ->approved()
            ->count();

        Log::info('ApprovalService: Current approval level', [
            'request_id' => $requestId,
            'module_option' => $moduleOption,
            'level' => $count
        ]);

        return $count;
    }

    /**
     * Check if user can approve a specific request.
     * Returns true if user is the next approver in the chain.
     */
    public function canUserApprove(int $userId, int $requestId, int $employeeId, string $moduleOption): bool
    {
        $approvalChain = $this->getRequiredApprovalLevels($employeeId);
        
        if (empty($approvalChain)) {
            Log::warning('ApprovalService: No approval chain configured', [
                'user_id' => $userId,
                'employee_id' => $employeeId
            ]);
            return false;
        }

        $currentLevel = $this->getCurrentApprovalLevel($requestId, $moduleOption);

        // Check if this user is the next approver
        $canApprove = isset($approvalChain[$currentLevel]) && $approvalChain[$currentLevel] == $userId;

        Log::info('ApprovalService: Can user approve', [
            'user_id' => $userId,
            'request_id' => $requestId,
            'current_level' => $currentLevel,
            'next_approver' => $approvalChain[$currentLevel] ?? null,
            'can_approve' => $canApprove
        ]);

        return $canApprove;
    }

    /**
     * Record an approval or rejection in the database.
     * 
     * @param int $requestId The request ID
     * @param int $approverId The user ID who is approving/rejecting
     * @param int $status 1=approved, 2=rejected
     * @param int $approvalLevel 0=intermediate, 1=final, 2=rejection
     * @param string $moduleOption Module identifier (e.g., 'overtime_request_settings')
     * @param int $companyId Company ID
     */
    public function recordApproval(
        int $requestId, 
        int $approverId, 
        int $status, 
        int $approvalLevel,
        string $moduleOption,
        int $companyId
    ): StaffApproval {
        $data = [
            'company_id' => $companyId,
            'staff_id' => $approverId,
            'module_key_id' => $requestId,
            'module_option' => $moduleOption,
            'status' => $status,
            'approval_level' => $approvalLevel,
            'updated_at' => date('d-m-Y H:i:s'),
        ];

        Log::info('ApprovalService: Recording approval', $data);

        return StaffApproval::create($data);
    }

    /**
     * Check if this is the final approval needed.
     * Returns true if all required approvers have approved.
     */
    public function isFinalApproval(int $requestId, int $employeeId, string $moduleOption): bool
    {
        $approvalChain = $this->getRequiredApprovalLevels($employeeId);
        $totalLevels = count($approvalChain);
        
        if ($totalLevels === 0) {
            return true; // No approval chain means first approval is final
        }

        $currentLevel = $this->getCurrentApprovalLevel($requestId, $moduleOption);
        
        // Final approval is when current level + 1 equals total levels
        $isFinal = ($currentLevel + 1) === $totalLevels;

        Log::info('ApprovalService: Is final approval', [
            'request_id' => $requestId,
            'employee_id' => $employeeId,
            'current_level' => $currentLevel,
            'total_levels' => $totalLevels,
            'is_final' => $isFinal
        ]);

        return $isFinal;
    }

    /**
     * Get total number of required approval levels.
     */
    public function getTotalApprovalLevels(int $employeeId): int
    {
        $approvalChain = $this->getRequiredApprovalLevels($employeeId);
        return count($approvalChain);
    }

    /**
     * Get all approvals for a request.
     */
    public function getApprovals(int $requestId, string $moduleOption): array
    {
        return StaffApproval::where('module_option', $moduleOption)
            ->forRequest($requestId)
            ->with('staff')
            ->orderBy('staff_approval_id', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Check if request has been rejected.
     */
    public function hasBeenRejected(int $requestId, string $moduleOption): bool
    {
        return StaffApproval::where('module_option', $moduleOption)
            ->forRequest($requestId)
            ->rejected()
            ->exists();
    }
}

