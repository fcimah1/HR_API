<?php

namespace App\DTOs\Transfer;

class CompanyApprovalDTO
{
    public function __construct(
        public readonly string $action,        // approve|reject
        public readonly string $approvalType,  // current_company|new_company
        public readonly int $approvedBy,
        public readonly ?string $remarks = null,
    ) {
        // Validate action
        if (!in_array($this->action, ['approve', 'reject'])) {
            throw new \InvalidArgumentException('Action must be either "approve" or "reject"');
        }

        // Validate approval type
        if (!in_array($this->approvalType, ['current_company', 'new_company'])) {
            throw new \InvalidArgumentException('Approval type must be either "current_company" or "new_company"');
        }
    }

    /**
     * Create DTO from request data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            action: $data['action'],
            approvalType: $data['approval_type'],
            approvedBy: $data['approved_by'],
            remarks: $data['remarks'] ?? null,
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'approval_type' => $this->approvalType,
            'approved_by' => $this->approvedBy,
            'remarks' => $this->remarks,
        ];
    }

    /**
     * Check if action is approve
     */
    public function isApprove(): bool
    {
        return $this->action === 'approve';
    }

    /**
     * Check if action is reject
     */
    public function isReject(): bool
    {
        return $this->action === 'reject';
    }

    /**
     * Check if approval is for current company
     */
    public function isCurrentCompany(): bool
    {
        return $this->approvalType === 'current_company';
    }

    /**
     * Check if approval is for new company
     */
    public function isNewCompany(): bool
    {
        return $this->approvalType === 'new_company';
    }
}
