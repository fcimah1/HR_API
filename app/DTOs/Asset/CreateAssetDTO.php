<?php

namespace App\DTOs\Asset;

class CreateAssetDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $categoryId,
        public readonly int $brandId,
        public readonly string $name,
        public readonly ?string $companyAssetCode = null,
        public readonly ?string $purchaseDate = null,
        public readonly ?string $invoiceNumber = null,
        public readonly ?string $manufacturer = null,
        public readonly ?string $serialNumber = null,
        public readonly ?string $warrantyEndDate = null,
        public readonly ?string $assetNote = null,
        public readonly ?string $assetImage = null,
        public readonly bool $isWorking = true,
        public readonly int $employeeId = 0
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            companyId: (int) $data['company_id'],
            categoryId: (int) $data['assets_category_id'],
            brandId: (int) $data['brand_id'],
            name: $data['name'],
            companyAssetCode: $data['company_asset_code'] ?? null,
            purchaseDate: $data['purchase_date'] ?? null,
            invoiceNumber: $data['invoice_number'] ?? null,
            manufacturer: $data['manufacturer'] ?? null,
            serialNumber: $data['serial_number'] ?? null,
            warrantyEndDate: $data['warranty_end_date'] ?? null,
            assetNote: $data['asset_note'] ?? null,
            assetImage: $data['asset_image'] ?? null,
            isWorking: isset($data['is_working']) ? (bool) $data['is_working'] : true,
            employeeId: isset($data['employee_id']) ? (int) $data['employee_id'] : 0
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'assets_category_id' => $this->categoryId,
            'brand_id' => $this->brandId,
            'name' => $this->name,
            'company_asset_code' => $this->companyAssetCode ?? '',
            'purchase_date' => $this->purchaseDate ?? '',
            'invoice_number' => $this->invoiceNumber ?? '',
            'manufacturer' => $this->manufacturer ?? '',
            'serial_number' => $this->serialNumber ?? '',
            'warranty_end_date' => $this->warrantyEndDate ?? '',
            'asset_note' => $this->assetNote ?? '',
            'asset_image' => $this->assetImage ?? '',
            'is_working' => $this->isWorking ? 1 : 0,
            'employee_id' => $this->employeeId,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
}

