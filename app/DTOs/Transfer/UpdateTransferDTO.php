<?php

namespace App\DTOs\Transfer;

use Spatie\LaravelData\Data;

class UpdateTransferDTO extends Data
{
    public function __construct(
        public readonly ?string $transferDate = null,
        public readonly ?int $transferDepartment = null,
        public readonly ?int $transferDesignation = null,
        public readonly ?int $newSalary = null,
        public readonly ?string $reason = null,
        public readonly ?int $newBranchId = null,
        public readonly ?int $newCompanyId = null,
        public readonly ?int $newCurrency = null,
        public readonly ?array $notifySendTo = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            transferDate: $data['transfer_date'] ?? null,
            transferDepartment: isset($data['transfer_department']) ? (int)$data['transfer_department'] : null,
            transferDesignation: isset($data['transfer_designation']) ? (int)$data['transfer_designation'] : null,
            newSalary: isset($data['new_salary']) ? (int)$data['new_salary'] : null,
            reason: $data['reason'] ?? null,
            newBranchId: isset($data['new_branch_id']) ? (int)$data['new_branch_id'] : null,
            newCompanyId: isset($data['new_company_id']) ? (int)$data['new_company_id'] : null,
            newCurrency: isset($data['new_currency']) ? (int)$data['new_currency'] : null,
            notifySendTo: $data['notify_send_to'] ?? null,
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->transferDate !== null) {
            $data['transfer_date'] = $this->transferDate;
        }

        if ($this->transferDepartment !== null) {
            $data['transfer_department'] = $this->transferDepartment;
        }

        if ($this->transferDesignation !== null) {
            $data['transfer_designation'] = $this->transferDesignation;
        }

        if ($this->newSalary !== null) {
            $data['new_salary'] = $this->newSalary;
        }

        if ($this->reason !== null) {
            $data['reason'] = $this->reason;
        }

        if ($this->newBranchId !== null) {
            $data['new_branch_id'] = $this->newBranchId;
        }

        if ($this->newCompanyId !== null) {
            $data['new_company_id'] = $this->newCompanyId;
        }

        if ($this->newCurrency !== null) {
            $data['new_currency'] = $this->newCurrency;
        }

        if ($this->notifySendTo !== null) {
            $data['notify_send_to'] = $this->notifySendTo;
        }

        return $data;
    }
}
