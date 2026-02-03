<?php

namespace App\DTOs\Asset;

use Illuminate\Http\Request;

class AssetFilterDTO
{
    public function __construct(
        public readonly ?string $search,
        public readonly ?string $assetStatus,
        public readonly ?int $employeeId,
        public readonly ?int $categoryId,
        public readonly ?int $brandId,
        public readonly int $perPage = 15
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: $request->input('search'),
            assetStatus: $request->input('asset_status'),
            employeeId: $request->input('employee_id') ? (int) $request->input('employee_id') : null,
            categoryId: $request->input('category_id') ? (int) $request->input('category_id') : null,
            brandId: $request->input('brand_id') ? (int) $request->input('brand_id') : null,
            perPage: (int) $request->input('per_page', 15)
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'search' => $this->search,
            'asset_status' => $this->assetStatus,
            'employee_id' => $this->employeeId,
            'category_id' => $this->categoryId,
            'brand_id' => $this->brandId,
        ], fn($value) => !is_null($value));
    }
}
