<?php

namespace App\DTOs\TravelType;

class UpdateTravelTypeDTO
{
    public function __construct(
        public ?string $travel_name = null
    ) {}

    public static function fromRequest($request): self
    {
        return new self(
            travel_name: $request->input('travel_name')
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->travel_name !== null) {
            $data['category_name'] = $this->travel_name;
        }

        return $data;
    }
}
