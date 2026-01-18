<?php

namespace App\DTOs\Country;

use Illuminate\Http\Request;

class CountryFilterDTO
{
    public function __construct(
        public ?string $search = null,
        public ?string $country_id = null,
        public ?string $country_name = null,
        public ?string $country_code = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: $request->input('search'),
            country_id: $request->input('country_id'),
            country_name: $request->input('country_name'),
            country_code: $request->input('country_code'),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'search' => $this->search,
            'country_id' => $this->country_id,
            'country_name' => $this->country_name,
            'country_code' => $this->country_code,
        ], fn($value) => !is_null($value));
    }
}
