<?php

namespace App\DTOs\Transfer;

use App\Models\Transfer;
use Spatie\LaravelData\Data;

class CreateTransferDTO extends Data
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $employeeId,
        public readonly string $transferDate,
        public readonly ?int $transferDepartment,
        public readonly ?int $transferDesignation,
        public readonly string $reason,
        public readonly int $addedBy,
        public readonly ?int $oldSalary = null,
        public readonly ?int $oldDesignation = null,
        public readonly ?int $oldDepartment = null,
        public readonly ?int $newSalary = null,
        public readonly ?int $oldCompanyId = null,
        public readonly ?int $oldBranchId = null,
        public readonly ?int $newCompanyId = null,
        public readonly ?int $newBranchId = null,
        public readonly ?int $oldCurrency = null,
        public readonly ?int $newCurrency = null,
        public readonly string $transferType = Transfer::TYPE_INTERNAL,
        public readonly ?int $currentCompanyApproval = null,
        public readonly ?int $newCompanyApproval = null,
        public readonly int $status = Transfer::STATUS_PENDING,
    ) {}

    public static function fromRequest(array $data, int $companyId, int $employeeId, int $addedBy): self
    {
        // تحديد نوع النقل تلقائياً
        $transferType = $data['transfer_type'] ?? Transfer::TYPE_INTERNAL;

        // تحديد حالات الموافقة بناءً على نوع النقل
        $currentCompanyApproval = null;
        $newCompanyApproval = null;

        if ($transferType === Transfer::TYPE_INTERCOMPANY) {
            $currentCompanyApproval = Transfer::APPROVAL_PENDING;
            $newCompanyApproval = Transfer::APPROVAL_PENDING;
        }

        return new self(
            companyId: $companyId,
            employeeId: $employeeId,
            transferDate: $data['transfer_date'],
            transferDepartment: $data['transfer_department'] ?? null,
            transferDesignation: $data['transfer_designation'] ?? null,
            reason: $data['reason'],
            addedBy: $addedBy,
            oldSalary: $data['old_salary'] ?? null,
            oldDesignation: $data['old_designation'] ?? null,
            oldDepartment: $data['old_department'] ?? null,
            newSalary: $data['new_salary'] ?? null,
            oldCompanyId: $data['old_company_id'] ?? null,
            oldBranchId: $data['old_branch_id'] ?? null,
            newCompanyId: $data['new_company_id'] ?? null,
            newBranchId: $data['new_branch_id'] ?? null,
            oldCurrency: $data['old_currency'] ?? null,
            newCurrency: $data['new_currency'] ?? null,
            transferType: $transferType,
            currentCompanyApproval: $currentCompanyApproval,
            newCompanyApproval: $newCompanyApproval,
            status: Transfer::STATUS_PENDING,
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'transfer_date' => $this->transferDate,
            'transfer_department' => $this->transferDepartment,
            'transfer_designation' => $this->transferDesignation,
            'reason' => $this->reason,
            'added_by' => $this->addedBy,
            'old_salary' => $this->oldSalary,
            'old_designation' => $this->oldDesignation,
            'old_department' => $this->oldDepartment,
            'new_salary' => $this->newSalary,
            'old_company_id' => $this->oldCompanyId,
            'old_branch_id' => $this->oldBranchId,
            'new_company_id' => $this->newCompanyId,
            'new_branch_id' => $this->newBranchId,
            'old_currency' => $this->oldCurrency,
            'new_currency' => $this->newCurrency,
            'transfer_type' => $this->transferType,
            'current_company_approval' => $this->currentCompanyApproval,
            'new_company_approval' => $this->newCompanyApproval,
            'status' => $this->status,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
