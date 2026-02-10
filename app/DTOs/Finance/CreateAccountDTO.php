<?php

namespace App\DTOs\Finance;

use Illuminate\Http\Request;

class CreateAccountDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly string $accountName,
        public readonly float $accountBalance,
        public readonly float $accountOpeningBalance,
        public readonly ?string $accountNumber,
        public readonly ?string $branchCode,
        public readonly ?string $bankBranch
    ) {}

    public static function fromRequest(Request $request, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            accountName: $request->input('account_name'),
            accountBalance: (float) $request->input('account_balance', 0),
            accountOpeningBalance: (float) $request->input('account_opening_balance', 0),
            accountNumber: $request->input('account_number'),
            branchCode: $request->input('branch_code'),
            bankBranch: $request->input('bank_branch')
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'account_name' => $this->accountName,
            'account_balance' => $this->accountBalance,
            'account_opening_balance' => $this->accountOpeningBalance,
            'account_number' => $this->accountNumber,
            'branch_code' => $this->branchCode,
            'bank_branch' => $this->bankBranch,
        ];
    }
}
