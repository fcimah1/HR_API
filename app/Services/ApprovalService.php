<?php

namespace App\Services;

use App\Models\StaffApproval;
use App\Models\UserDetails;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Services\PushNotificationService;

/**
 * Reusable multi-level approval service for all modules.
 * Handles approval workflow tracking in ci_erp_notifications_approval table.
 * 🔥 INTEGRATED WITH FCM NOTIFICATIONS
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
        $count = StaffApproval::where('module_option', $moduleOption)
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

        // If no approval chain configured
        if (empty($approvalChain)) {
            Log::info('ApprovalService: No approval chain, checking hierarchy', [
                'employee_id' => $employeeId,
                'user_id' => $userId,
                'message' => 'ليس لديك صلاحية لإعتماد هذا الطلب'
            ]);

            // Fallback: Check if user has hierarchical permission to approve
            try {
                $permissionService = app(\App\Services\SimplePermissionService::class);
                $user = User::find($userId);
                $employee = User::find($employeeId);

                if ($user && $employee && $permissionService->canApproveEmployeeRequests($user, $employee)) {
                    Log::info('ApprovalService: Hierarchy check passed', [
                        'employee_id' => $employeeId,
                        'user_id' => $userId,
                        'message' => ' تمت الموافقة على هذا الطلب __ ملحوظة: هذا الموظف ليس لديه سلسلة اعتماد مُعدة'
                    ]);
                    return true;
                }
            } catch (\Exception $e) {
                Log::error('ApprovalService: Hierarchy check failed', [
                    'error' => $e->getMessage(),
                    'employee_id' => $employeeId,
                    'user_id' => $userId,
                    'message' => 'ليس لديك صلاحية لإعتماد هذا الطلب'
                ]);
            }

            Log::warning('ApprovalService: No approval chain and hierarchy check failed', [
                'employee_id' => $employeeId,
                'user_id' => $userId,
                'message' => 'ليس لديك صلاحية لإعتماد هذا الطلب'
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
     * Get detailed info about why user cannot approve.
     */
    public function getApprovalDenialReason(int $userId, int $requestId, int $employeeId, string $moduleOption): array
    {
        $approvalChain = $this->getRequiredApprovalLevels($employeeId);

        if (empty($approvalChain)) {
            return [
                'reason' => 'no_chain',
                'message' => 'لا توجد سلسلة اعتماد مُعدة لهذا الموظف',
                'next_approver' => null,
                'current_level' => 0,
                'total_levels' => 0,
            ];
        }

        $currentLevel = $this->getCurrentApprovalLevel($requestId, $moduleOption);
        $totalLevels = count($approvalChain);

        // Check if all approvals done
        if ($currentLevel >= $totalLevels) {
            return [
                'reason' => 'already_fully_approved',
                'message' => 'تمت الموافقة على هذا الطلب بالكامل مسبقاً',
                'next_approver' => null,
                'current_level' => $currentLevel,
                'total_levels' => $totalLevels,
            ];
        }

        $nextApproverId = $approvalChain[$currentLevel];
        $nextApprover = User::find($nextApproverId);

        $nextApproverName = $nextApprover
            ? trim($nextApprover->first_name . ' ' . $nextApprover->last_name)
            : 'غير معروف';

        // Check if user is in the chain at all
        $userPositionInChain = array_search($userId, $approvalChain);

        if ($userPositionInChain === false) {
            return [
                'reason' => 'not_in_chain',
                'message' => "أنت لست ضمن سلسلة الاعتماد لهذا الموظف. المعتمد التالي المطلوب: {$nextApproverName}",
                'next_approver' => [
                    'user_id' => $nextApproverId,
                    'name' => $nextApproverName,
                    'level' => $currentLevel + 1,
                ],
                'current_level' => $currentLevel,
                'total_levels' => $totalLevels,
            ];
        }

        // User is in chain but not their turn
        $userLevel = $userPositionInChain + 1;
        $requiredLevel = $currentLevel + 1;

        return [
            'reason' => 'not_your_turn',
            'message' => "يجب أن يوافق {$nextApproverName} (المستوى {$requiredLevel}) أولاً. أنت في المستوى {$userLevel} من سلسلة الاعتماد.",
            'next_approver' => [
                'user_id' => $nextApproverId,
                'name' => $nextApproverName,
                'level' => $requiredLevel,
            ],
            'your_level' => $userLevel,
            'current_level' => $currentLevel,
            'total_levels' => $totalLevels,
        ];
    }

    /**
     * 🔥 Record approval AND send FCM notifications
     */
    public function recordApproval(
        int $requestId,
        int $approverId,
        int $status,
        int $approvalLevel,
        string $moduleOption,
        int $companyId,
        int $submitterId  // 🔥 NEW: Pass submitter ID
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
        $approval = StaffApproval::create($data);

        // 🔥 SEND FCM NOTIFICATIONS
        $this->sendApprovalNotifications($submitterId, $approverId, $status, $moduleOption, $requestId);

        return $approval;
    }

    /**
     * 🔥 Send FCM notifications to submitter + managers
     */
    private function sendApprovalNotifications(int $submitterId, int $approverId, int $status, string $moduleOption, int $requestId): void
    {
        try {
            Log::debug('resolveNotifiers: Started', ['submitterId' => $submitterId]);
            $notifierIds = $this->resolveNotifiers($submitterId);
            
            if (empty($notifierIds)) {
                Log::warning('resolveNotifiers: No notifiers found', ['submitterId' => $submitterId]);
                return;
            }

            $fcm = new PushNotificationService();
            $statusText = $status === 1 ? '✅ تمت الموافقة' : '❌ تم الرفض';
            $title = "{$statusText} - {$moduleOption}";
            $body = "رقم الطلب: #{$requestId}";

            foreach ($notifierIds as $userId) {
                $success = $fcm->sendToUser($userId, $title, $body);
                Log::info('FCM Result', [
                    'user_id' => $userId,
                    'approver_id' => $approverId,
                    'request_id' => $requestId,
                    'success' => $success
                ]);
            }

            Log::info('FCM: Approval notifications sent', [
                'request_id' => $requestId,
                'submitter_id' => $submitterId,
                'notifier_ids' => $notifierIds,
                'status' => $statusText
            ]);

        } catch (\Exception $e) {
            Log::error('FCM: Approval notification failed', [
                'error' => $e->getMessage(),
                'submitter_id' => $submitterId,
                'request_id' => $requestId
            ]);
        }
    }

    /**
     * 🔥 Resolve notification recipients (self + manager)
     */
    protected function resolveNotifiers(int $submitterId): array
    {
        $notifiers = [$submitterId]; // Self always gets notified
        
        Log::debug('resolveNotifiers: Added self', ['submitterId' => $submitterId]);

        // Add manager
        $userDetails = UserDetails::where('user_id', $submitterId)->first();
        if ($userDetails && $userDetails->reporting_manager) {
            $notifiers[] = $userDetails->reporting_manager;
            Log::debug('resolveNotifiers: Added manager', [
                'submitterId' => $submitterId, 
                'managerId' => $userDetails->reporting_manager
            ]);
        } else {
            Log::debug('resolveNotifiers: No reporting_manager found', [
                'submitterId' => $submitterId,
                'hasDetails' => !!$userDetails
            ]);
        }

        Log::info('resolveNotifiers: Final resolved IDs', ['resolvedIds' => $notifiers]);
        return $notifiers;
    }

    /**
     * Check if this is the final approval needed.
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
