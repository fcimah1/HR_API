<?php

declare(strict_types=1);

namespace App\DTOs\ContractOption;

class StoreContractOptionDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $userId,
        public readonly string $type,
        public readonly int $contractTaxOption,
        public readonly int $isFixed,
        public readonly string $optionTitle,
        public readonly float $contractAmount,
    ) {}

    public static function fromRequest(array $data, int $companyId, int $userId, string $type): self
    {
        return new self(
            companyId: $companyId,
            userId: $userId,
            type: $type,
            contractTaxOption: (int) ($data['contract_tax_option'] ?? 0),
            isFixed: (int) $data['is_fixed'],
            optionTitle: (string) $data['option_title'],
            contractAmount: (float) ($data['contract_amount'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'user_id' => $this->userId,
            'salay_type' => $this->type,
            'contract_tax_option' => $this->contractTaxOption,
            'is_fixed' => $this->isFixed,
            'option_title' => $this->optionTitle,
            'contract_amount' => $this->contractAmount,
        ];
    }
}
