<?php

namespace App\DTOs\CustodyClearance;

use App\Models\Asset;

class CustodyResponseDTO
{
    public function __construct(
        public readonly int $assetId,
        public readonly int $companyId,
        public readonly string $name,
        public readonly ?string $serialNumber,
        public readonly ?string $companyAssetCode,
        public readonly ?string $status,
        public readonly ?string $purchaseDate,
        public readonly ?string $allocatedDate,
        public readonly ?int $employeeId,
        public readonly ?string $employeeName,
        public readonly ?string $brandName,
        public readonly ?string $assetsCategory,
    ) {}

    public static function fromModel(Asset $asset): self
    {
        return new self(
            assetId: $asset->assets_id,
            companyId: $asset->company_id,
            name: $asset->name,
            serialNumber: $asset->serial_number,
            companyAssetCode: $asset->company_asset_code,
            status: $asset->is_working ? 'working' : 'not_working',
            purchaseDate: $asset->purchase_date?->format('Y-m-d'),
            allocatedDate: $asset->created_at?->format('Y-m-d'), // Using created_at as fallback since allocated_date doesn't exist
            employeeId: $asset->employee_id,
            employeeName: $asset->employee ? trim($asset->employee->first_name . ' ' . $asset->employee->last_name) : null,
            brandName: $asset->brand ? $asset->brand->category_name : null,
            assetsCategory: $asset->category ? $asset->category->category_name : null,
        );
    }

    public function toArray(): array
    {
        return [
            'asset_id' => $this->assetId,
            'company_id' => $this->companyId,
            'name' => $this->name,
            'serial_number' => $this->serialNumber,
            'company_asset_code' => $this->companyAssetCode,
            'status' => $this->status,
            'purchase_date' => $this->purchaseDate,
            'allocated_date' => $this->allocatedDate,
            'employee_id' => $this->employeeId,
            'employee_name' => $this->employeeName,
            'brand_name' => $this->brandName,
            'assets_category' => $this->assetsCategory,
        ];
    }
}
