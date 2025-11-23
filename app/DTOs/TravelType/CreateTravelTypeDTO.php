<?php

namespace App\DTOs\TravelType;

class CreateTravelTypeDTO
{
    public function __construct(
        public string $travel_name,
        public int $company_id
    ) {}

    public static function fromRequest($request, int $companyId): self
    {
        return new self(
            travel_name: $request->input('travel_name'),
            company_id: $companyId
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->company_id,
            'type' => 'travel_type',
            'category_name' => $this->travel_name,
            'field_one' => null,
            'field_two' => null,
            'field_three' => null,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
