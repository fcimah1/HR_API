<?php

declare(strict_types=1);

namespace App\DTOs\Warehouse;

class CreateWarehouseDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly string $warehouseName,
        public readonly ?string $contactNumber,
        public readonly int $pickupLocation,
        public readonly ?string $address1,
        public readonly ?string $address2,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $zipcode,
        public readonly int $country,
        public readonly int $addedBy,
        public readonly int $status,
    ) {}

    public static function fromRequest(array $data, int $companyId, int $userId): self
    {
        return new self(
            companyId: $companyId,
            warehouseName: $data['warehouse_name'],
            contactNumber: $data['contact_number'] ?? null,
            pickupLocation: (int) ($data['pickup_location'] ?? 1),
            address1: $data['address_1'] ?? null,
            address2: $data['address_2'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? null,
            zipcode: $data['zipcode'] ?? null,
            country: (int) ($data['country'] ?? 0),
            addedBy: $userId,
            status: (int) ($data['status'] ?? 1),
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'warehouse_name' => $this->warehouseName,
            'contact_number' => $this->contactNumber,
            'pickup_location' => $this->pickupLocation,
            'address_1' => $this->address1,
            'address_2' => $this->address2,
            'city' => $this->city,
            'state' => $this->state,
            'zipcode' => $this->zipcode,
            'country' => $this->country,
            'added_by' => $this->addedBy,
            'status' => $this->status,
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
