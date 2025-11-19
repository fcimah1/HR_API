<?php

namespace App\DTOs\Asset;

class UpdateAssetDTO
{
    public function __construct(
        public readonly int $assetId,
        public readonly ?int $categoryId = null,
        public readonly ?int $brandId = null,
        public readonly ?string $name = null,
        public readonly ?string $companyAssetCode = null,
        public readonly ?string $purchaseDate = null,
        public readonly ?string $invoiceNumber = null,
        public readonly ?string $manufacturer = null,
        public readonly ?string $serialNumber = null,
        public readonly ?string $warrantyEndDate = null,
        public readonly ?string $assetNote = null,
        public readonly ?string $assetImage = null,
        public readonly ?bool $isWorking = null
    ) {}

    public static function fromRequest(int $assetId, array $data): self
    {
        return new self(
            assetId: $assetId,
            categoryId: isset($data['assets_category_id']) ? (int) $data['assets_category_id'] : null,
            brandId: isset($data['brand_id']) ? (int) $data['brand_id'] : null,
            name: $data['name'] ?? null,
            companyAssetCode: $data['company_asset_code'] ?? null,
            purchaseDate: $data['purchase_date'] ?? null,
            invoiceNumber: $data['invoice_number'] ?? null,
            manufacturer: $data['manufacturer'] ?? null,
            serialNumber: $data['serial_number'] ?? null,
            warrantyEndDate: $data['warranty_end_date'] ?? null,
            assetNote: $data['asset_note'] ?? null,
            assetImage: $data['asset_image'] ?? null,
            isWorking: isset($data['is_working']) ? (bool) $data['is_working'] : null
        );
    }

    public function toArray(): array
    {
        $data = [];
        
        if ($this->categoryId !== null) {
            $data['assets_category_id'] = $this->categoryId;
        }
        if ($this->brandId !== null) {
            $data['brand_id'] = $this->brandId;
        }
        if ($this->name !== null) {
            $data['name'] = $this->name;
        }
        if ($this->companyAssetCode !== null) {
            $data['company_asset_code'] = $this->companyAssetCode;
        }
        if ($this->purchaseDate !== null) {
            $data['purchase_date'] = $this->purchaseDate;
        }
        if ($this->invoiceNumber !== null) {
            $data['invoice_number'] = $this->invoiceNumber;
        }
        if ($this->manufacturer !== null) {
            $data['manufacturer'] = $this->manufacturer;
        }
        if ($this->serialNumber !== null) {
            $data['serial_number'] = $this->serialNumber;
        }
        if ($this->warrantyEndDate !== null) {
            $data['warranty_end_date'] = $this->warrantyEndDate;
        }
        if ($this->assetNote !== null) {
            $data['asset_note'] = $this->assetNote;
        }
        if ($this->assetImage !== null) {
            $data['asset_image'] = $this->assetImage;
        }
        if ($this->isWorking !== null) {
            $data['is_working'] = $this->isWorking ? 1 : 0;
        }

        return $data;
    }

    public function hasChanges(): bool
    {
        return !empty($this->toArray());
    }
}

