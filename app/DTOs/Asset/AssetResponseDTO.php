<?php

namespace App\DTOs\Asset;

use App\Models\Asset;

class AssetResponseDTO
{
    public function __construct(
        public readonly int $assetId,
        public readonly int $companyId,
        public readonly int $categoryId,
        public readonly ?string $categoryName = null,
        public readonly int $brandId,
        public readonly ?string $brandName = null,
        public readonly ?int $employeeId = null,
        public readonly ?array $employee = null,
        public readonly ?string $companyAssetCode = null,
        public readonly string $name,
        public readonly ?string $purchaseDate = null,
        public readonly ?string $invoiceNumber = null,
        public readonly ?string $manufacturer = null,
        public readonly ?string $serialNumber = null,
        public readonly ?string $warrantyEndDate = null,
        public readonly ?string $assetNote = null,
        public readonly ?string $assetImage = null,
        public readonly bool $isWorking,
        public readonly bool $isAssigned,
        public readonly ?bool $isWarrantyValid = null,
        public readonly ?string $createdAt = null
    ) {}

    public static function fromModel(Asset $asset): self
    {
        $employee = null;
        if ($asset->relationLoaded('employee') && $asset->employee) {
            $employee = [
                'user_id' => $asset->employee->user_id,
                'first_name' => $asset->employee->first_name,
                'last_name' => $asset->employee->last_name,
                'full_name' => $asset->employee->first_name . ' ' . $asset->employee->last_name,
                'email' => $asset->employee->email,
            ];
        }

        $categoryName = null;
        if ($asset->relationLoaded('category') && $asset->category) {
            $categoryName = $asset->category->category_name;
        }

        $brandName = null;
        if ($asset->relationLoaded('brand') && $asset->brand) {
            $brandName = $asset->brand->category_name;
        }

        $isWarrantyValid = null;
        if ($asset->warranty_end_date) {
            try {
                $warrantyDate = \Carbon\Carbon::parse($asset->warranty_end_date);
                $isWarrantyValid = $warrantyDate->isFuture();
            } catch (\Exception $e) {
                $isWarrantyValid = false;
            }
        }

        return new self(
            assetId: $asset->assets_id,
            companyId: $asset->company_id,
            categoryId: $asset->assets_category_id,
            categoryName: $categoryName,
            brandId: $asset->brand_id,
            brandName: $brandName,
            employeeId: $asset->employee_id > 0 ? $asset->employee_id : null,
            employee: $employee,
            companyAssetCode: $asset->company_asset_code,
            name: $asset->name,
            purchaseDate: $asset->purchase_date,
            invoiceNumber: $asset->invoice_number,
            manufacturer: $asset->manufacturer,
            serialNumber: $asset->serial_number,
            warrantyEndDate: $asset->warranty_end_date,
            assetNote: $asset->asset_note,
            assetImage: $asset->asset_image,
            isWorking: (bool) $asset->is_working,
            isAssigned: $asset->isAssigned(),
            isWarrantyValid: $isWarrantyValid,
            createdAt: $asset->created_at
        );
    }

    public function toArray(): array
    {
        $data = [
            'asset_id' => $this->assetId,
            'company_id' => $this->companyId,
            'category' => [
                'id' => $this->categoryId,
                'name' => $this->categoryName,
            ],
            'brand' => [
                'id' => $this->brandId,
                'name' => $this->brandName,
            ],
            'name' => $this->name,
            'company_asset_code' => $this->companyAssetCode,
            'purchase_date' => $this->purchaseDate,
            'invoice_number' => $this->invoiceNumber,
            'manufacturer' => $this->manufacturer,
            'serial_number' => $this->serialNumber,
            'warranty_end_date' => $this->warrantyEndDate,
            'is_warranty_valid' => $this->isWarrantyValid,
            'asset_note' => $this->assetNote,
            'asset_image' => $this->assetImage,
            'is_working' => $this->isWorking,
            'is_assigned' => $this->isAssigned,
            'created_at' => $this->createdAt,
        ];

        if ($this->employeeId !== null) {
            $data['employee_id'] = $this->employeeId;
            if ($this->employee !== null) {
                $data['employee'] = $this->employee;
            }
        }

        return $data;
    }
}

