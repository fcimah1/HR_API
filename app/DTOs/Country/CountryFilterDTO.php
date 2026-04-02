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
        public ?bool $paginate = true,
        public ?int $per_page = 10,
        public ?int $page = 1,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: $request->input('search'),
            country_id: $request->input('country_id'),
            country_name: $request->input('country_name'),
            country_code: $request->input('country_code'),
            paginate: filter_var($request->input('paginate', true), FILTER_VALIDATE_BOOLEAN),
            per_page: (int) $request->input('per_page', 10),
            page: (int) $request->input('page', 1),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'search' => $this->search,
            'country_id' => $this->country_id,
            'country_name' => $this->country_name,
            'country_code' => $this->country_code,
            'paginate' => $this->paginate,
            'per_page' => $this->per_page,
            'page' => $this->page,
        ], fn($value) => !is_null($value));
    }
}
