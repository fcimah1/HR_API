<?php

namespace App\DTOs\Award;

use Illuminate\Http\UploadedFile;

class UpdateAwardDTO
{
    public function __construct(
        public readonly int $awardId,
        public readonly ?int $employeeId,
        public readonly ?int $awardTypeId,
        public readonly ?string $awardDate,
        public readonly ?string $giftItem,
        public readonly ?float $cashPrice,
        public readonly ?string $description,
        public readonly ?string $awardInformation,
        public readonly ?UploadedFile $file = null
    ) {}

    public static function fromRequest(int $id, array $data): self
    {
        return new self(
            awardId: $id,
            employeeId: isset($data['employee_id']) ? (int) $data['employee_id'] : null,
            awardTypeId: isset($data['award_type_id']) ? (int) $data['award_type_id'] : null,
            awardDate: $data['award_date'] ?? null,
            giftItem: $data['gift_item'] ?? null,
            cashPrice: isset($data['cash_price']) ? (float) $data['cash_price'] : null,
            description: $data['description'] ?? null,
            awardInformation: $data['award_information'] ?? null, // Explicitly mapping award_information field
            file: $data['award_file'] ?? null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'employee_id' => $this->employeeId,
            'award_type_id' => $this->awardTypeId,
            'award_month_year' => $this->awardDate,
            'gift_item' => $this->giftItem,
            'cash_price' => $this->cashPrice,
            'description' => $this->description,
            'award_information' => $this->awardInformation,
        ], fn($value) => !is_null($value));
    }
}
