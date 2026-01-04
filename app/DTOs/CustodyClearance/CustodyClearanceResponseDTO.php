<?php

namespace App\DTOs\CustodyClearance;

use App\Models\CustodyClearance;

class CustodyClearanceResponseDTO
{
    public function __construct(
        public readonly int $clearanceId,
        public readonly int $companyId,
        public readonly int $employeeId,
        public readonly ?string $employeeName,
        public readonly string $clearanceDate,
        public readonly string $clearanceType,
        public readonly string $clearanceTypeText,
        public readonly ?string $notes,
        public readonly string $status,
        public readonly string $statusText,
        public readonly ?int $approvedBy,
        public readonly ?string $approverName,
        public readonly ?string $approvedDate,
        public readonly int $createdBy,
        public readonly ?string $creatorName,
        public readonly string $createdAt,
        public readonly array $items,
        public readonly array $approvals,
    ) {}

    public static function fromModel(CustodyClearance $clearance): self
    {
        $clearanceTypeTexts = [
            'resignation' => 'استقالة',
            'termination' => 'إنهاء خدمة',
            'transfer' => 'نقل',
            'other' => 'أخرى',
        ];

        $statusTexts = [
            'pending' => 'قيد الانتظار',
            'approved' => 'موافق عليه',
            'rejected' => 'مرفوض',
        ];

        $items = $clearance->items?->map(function ($item) {
            return [
                'item_id' => $item->item_id,
                'asset_id' => $item->asset_id,
                'asset_name' => $item->asset?->name ?? null,
                'asset_serial' => $item->asset?->serial_number ?? null,
                'asset_condition' => $item->asset_condition,
                'return_date' => $item->return_date?->format('Y-m-d'),
                'notes' => $item->notes,
            ];
        })->toArray() ?? [];

        $approvals = $clearance->approvals?->map(function ($approval) {
            return [
                'staff_approval_id' => $approval->staff_approval_id,
                'staff_id' => $approval->staff_id,
                'staff_name' => $approval->staff ? trim($approval->staff->first_name . ' ' . $approval->staff->last_name) : null,
                'status' => $approval->status,
                'approval_level' => $approval->approval_level,
                'updated_at' => $approval->updated_at?->format('d-m-Y H:i:s'),
            ];
        })->toArray() ?? [];

        return new self(
            clearanceId: $clearance->clearance_id,
            companyId: $clearance->company_id,
            employeeId: $clearance->employee_id,
            employeeName: $clearance->employee ? trim($clearance->employee->first_name . ' ' . $clearance->employee->last_name) : null,
            clearanceDate: $clearance->clearance_date->format('Y-m-d'),
            clearanceType: $clearance->clearance_type,
            clearanceTypeText: $clearanceTypeTexts[$clearance->clearance_type] ?? $clearance->clearance_type,
            notes: $clearance->notes,
            status: $clearance->status,
            statusText: $statusTexts[$clearance->status] ?? $clearance->status,
            approvedBy: $clearance->approved_by,
            approverName: $clearance->approver ? trim($clearance->approver->first_name . ' ' . $clearance->approver->last_name) : null,
            approvedDate: $clearance->approved_date?->format('d-m-Y H:i:s'),
            createdBy: $clearance->created_by,
            creatorName: $clearance->creator ? trim($clearance->creator->first_name . ' ' . $clearance->creator->last_name) : null,
            createdAt: $clearance->created_at->format('d-m-Y H:i:s'),
            items: $items,
            approvals: $approvals,
        );
    }

    public function toArray(): array
    {
        return [
            'clearance_id' => $this->clearanceId,
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'employee_name' => $this->employeeName,
            'clearance_date' => $this->clearanceDate,
            'clearance_type' => $this->clearanceType,
            'clearance_type_text' => $this->clearanceTypeText,
            'notes' => $this->notes,
            'status' => $this->status,
            'status_text' => $this->statusText,
            'approved_by' => $this->approvedBy,
            'approver_name' => $this->approverName,
            'approved_date' => $this->approvedDate,
            'created_by' => $this->createdBy,
            'creator_name' => $this->creatorName,
            'created_at' => $this->createdAt,
            'items' => $this->items,
            'approvals' => $this->approvals,
        ];
    }
}
