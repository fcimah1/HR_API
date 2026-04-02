<?php

declare(strict_types=1);

namespace App\DTOs\ContractOption;

class UpdateContractOptionDTO
{
    public function __construct(
        public readonly string $type,
        public readonly ?int $contractTaxOption,
        public readonly ?int $isFixed,
        public readonly ?string $optionTitle,
        public readonly ?float $contractAmount,
    ) {}

    public static function fromRequest(array $data, string $type): self
    {
        return new self(
            type: $type,
            contractTaxOption: array_key_exists('contract_tax_option', $data) ? (int) $data['contract_tax_option'] : null,
            isFixed: array_key_exists('is_fixed', $data) ? (int) $data['is_fixed'] : null,
            optionTitle: array_key_exists('option_title', $data) ? (string) $data['option_title'] : null,
            contractAmount: array_key_exists('contract_amount', $data) ? (float) $data['contract_amount'] : null,
        );
    }

    public function toArray(): array
    {
        $payload = [];

        if ($this->contractTaxOption !== null) {
            $payload['contract_tax_option'] = $this->contractTaxOption;
        }
        if ($this->isFixed !== null) {
            $payload['is_fixed'] = $this->isFixed;
        }
        if ($this->optionTitle !== null) {
            $payload['option_title'] = $this->optionTitle;
        }
        if ($this->contractAmount !== null) {
            $payload['contract_amount'] = $this->contractAmount;
        }

        $payload['salay_type'] = $this->type;

        return $payload;
    }
}
