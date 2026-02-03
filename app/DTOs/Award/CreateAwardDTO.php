<?php

namespace App\DTOs\Award;

use Illuminate\Http\UploadedFile;

class CreateAwardDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $employeeId,
        public readonly int $awardTypeId,
        public readonly string $awardDate, // award_month_year
        public readonly ?string $giftItem, // award_information
        public readonly ?float $cashPrice,
        public readonly ?string $description,
        public readonly ?UploadedFile $file = null
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            employeeId: (int) $data['employee_id'],
            awardTypeId: (int) $data['award_type_id'],
            awardDate: $data['award_date'],
            giftItem: $data['gift_item'] ?? null,
            cashPrice: isset($data['cash_price']) ? (float) $data['cash_price'] : null,
            description: $data['description'] ?? null,
            file: $data['award_file'] ?? null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'award_type_id' => $this->awardTypeId,
            'award_month_year' => $this->awardDate,
            'gift_item' => $this->giftItem, // Assuming gift_item maps to gift field in UI
            'award_information' => $this->giftItem, // Mapping 'gift_item' to 'award_information' based on old schema usage if needed, or vice-versa.
            // Let's assume database uses fields: gift_item, cash_price, award_information, description. 
            // In the model update I kept 'gift_item' and 'award_information'. 
            // Based on user screenshot: 
            // Fields: Employee, Award Type, Date, Gift (input), Cash (input), Description (textarea), Info (textarea - bottom one).
            // Let's map 'gift_item' to the 'Gift' input and 'award_information' to the 'Award Information' input.
            'cash_price' => $this->cashPrice,
            'description' => $this->description,
            // File handled in service
        ], fn($value) => !is_null($value));
    }
}
