<?php

namespace App\DTOs\Notification;

use Illuminate\Http\Request;

class ApprovalActionDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $staffId,
        public readonly string $moduleOption,
        public readonly string $moduleKeyId,
        public readonly int $status, // 1 = pending, 2 = approved, 3 = rejected
        public readonly ?int $approvalLevel = null,
    ) {}

    /**
     * Create DTO from request
     */
    public static function fromRequest(Request $request, int $approverId, int $companyId): self
    {
        $statusMap = [
            'approve' => 2,  // APPROVED
            'approved' => 2, // APPROVED
            'reject' => 3,   // REJECTED
            'rejected' => 3, // REJECTED
        ];

        $statusInput = strtolower($request->input('status'));
        $status = $statusMap[$statusInput] ?? 2; // Default to APPROVED

        return new self(
            companyId: $companyId,
            staffId: $approverId,
            moduleOption: $request->input('module_option'),
            moduleKeyId: $request->input('module_key_id'),
            status: $status,
            approvalLevel: $request->input('approval_level') ? (int)$request->input('approval_level') : null,
        );
    }

    /**
     * Convert to array for database
     */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'staff_id' => $this->staffId,
            'module_option' => $this->moduleOption,
            'module_key_id' => $this->moduleKeyId,
            'status' => $this->status,
            'approval_level' => $this->approvalLevel ?? 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Check if this is an approval
     */
    public function isApproval(): bool
    {
        return $this->status === 2; // APPROVED
    }

    /**
     * Check if this is a rejection
     */
    public function isRejection(): bool
    {
        return $this->status === 3; // REJECTED
    }
}
