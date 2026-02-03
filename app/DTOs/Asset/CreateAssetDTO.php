<?php

namespace App\DTOs\Asset;

use Illuminate\Http\UploadedFile;

class CreateAssetDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly string $name,
        public readonly ?int $categoryId,
        public readonly ?int $brandId,
        public readonly ?int $employeeId,
        public readonly ?string $companyAssetCode,
        public readonly ?string $purchaseDate,
        public readonly ?string $invoiceNumber,
        public readonly ?string $manufacturer,
        public readonly ?string $serialNumber,
        public readonly ?string $warrantyEndDate,
        public readonly ?string $assetNote,
        public readonly ?bool $isWorking,
        public readonly ?UploadedFile $image = null
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            name: $data['name'],
            categoryId: isset($data['assets_category_id']) ? (int) $data['assets_category_id'] : null,
            brandId: isset($data['brand_id']) ? (int) $data['brand_id'] : null,
            employeeId: isset($data['employee_id']) ? (int) $data['employee_id'] : null,
            companyAssetCode: $data['company_asset_code'] ?? null,
            purchaseDate: $data['purchase_date'] ?? null,
            invoiceNumber: $data['invoice_number'] ?? null,
            manufacturer: $data['manufacturer'] ?? '',
            serialNumber: $data['serial_number'] ?? null,
            warrantyEndDate: $data['warranty_end_date'] ?? null,
            assetNote: $data['asset_note'] ?? null,
            isWorking: isset($data['is_working']) ? (bool) $data['is_working'] : true,
            image: $data['asset_image'] ?? null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'company_id' => $this->companyId,
            'name' => $this->name,
            'assets_category_id' => $this->categoryId,
            'brand_id' => $this->brandId,
            'employee_id' => $this->employeeId,
            'company_asset_code' => $this->companyAssetCode,
            'purchase_date' => $this->purchaseDate,
            'invoice_number' => $this->invoiceNumber,
            'manufacturer' => $this->manufacturer,
            'serial_number' => $this->serialNumber,
            'warranty_end_date' => $this->warrantyEndDate,
            'asset_note' => $this->assetNote,
            'is_working' => $this->isWorking ? 1 : 0,
            // Image is handled separately in service
        ], fn($value) => !is_null($value));
    }
}
