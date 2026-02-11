<?php

declare(strict_types=1);

namespace App\DTOs\Warehouse;

class UpdateWarehouseDTO
{
    public function __construct(
        public readonly string $warehouseName,
        public readonly ?string $contactNumber,
        public readonly int $pickupLocation,
        public readonly ?string $address1,
        public readonly ?string $address2,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $zipcode,
        public readonly int $country,
        public readonly int $status,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            warehouseName: $data['warehouse_name'],
            contactNumber: $data['contact_number'] ?? null,
            pickupLocation: (int) ($data['pickup_location'] ?? 1),
            address1: $data['address_1'] ?? null,
            address2: $data['address_2'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? null,
            zipcode: $data['zipcode'] ?? null,
            country: (int) ($data['country'] ?? 0),
            status: (int) ($data['status'] ?? 1),
        );
    }

    public function toArray(): array
    {
        return [
            'warehouse_name' => $this->warehouseName,
            'contact_number' => $this->contactNumber,
            'pickup_location' => $this->pickupLocation,
            'address_1' => $this->address1,
            'address_2' => $this->address2,
            'city' => $this->city,
            'state' => $this->state,
            'zipcode' => $this->zipcode,
            'country' => $this->country,
            'status' => $this->status,
        ];
    }
}
