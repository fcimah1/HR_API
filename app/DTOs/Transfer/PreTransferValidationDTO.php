<?php

namespace App\DTOs\Transfer;

use Spatie\LaravelData\Data;

class PreTransferValidationDTO extends Data
{
    public function __construct(
        public readonly bool $canTransfer,
        public readonly array $activeLeaves,
        public readonly array $activeAdvances,
        public readonly array $unreturnedCustody,
    ) {}

    public static function fromValidationResults(
        array $leaves,
        array $advances,
        array $custody
    ): self {
        $canTransfer = empty($leaves) && empty($advances) && empty($custody);

        return new self(
            canTransfer: $canTransfer,
            activeLeaves: $leaves,
            activeAdvances: $advances,
            unreturnedCustody: $custody,
        );
    }

    public function toArray(): array
    {
        return [
            'can_transfer' => $this->canTransfer,
            'validations' => [
                'active_leaves' => [
                    'passed' => empty($this->activeLeaves),
                    'count' => count($this->activeLeaves),
                    'items' => $this->activeLeaves,
                ],
                'active_advances' => [
                    'passed' => empty($this->activeAdvances),
                    'count' => count($this->activeAdvances),
                    'items' => $this->activeAdvances,
                ],
                'unreturned_custody' => [
                    'passed' => empty($this->unreturnedCustody),
                    'count' => count($this->unreturnedCustody),
                    'items' => $this->unreturnedCustody,
                ],
            ],
        ];
    }
}
