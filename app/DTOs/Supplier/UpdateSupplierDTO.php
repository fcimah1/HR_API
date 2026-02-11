<?php

declare(strict_types=1);

namespace App\DTOs\Supplier;

class UpdateSupplierDTO
{
    public function __construct(
        public readonly string $supplierName,
        public readonly ?string $registrationNo,
        public readonly ?string $email,
        public readonly ?string $contactNumber,
        public readonly ?string $websiteUrl,
        public readonly ?string $address1,
        public readonly ?string $address2,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $zipcode,
        public readonly int $country,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            supplierName: $data['supplier_name'],
            registrationNo: $data['registration_no'] ?? null,
            email: $data['email'] ?? null,
            contactNumber: $data['contact_number'] ?? null,
            websiteUrl: $data['website_url'] ?? null,
            address1: $data['address_1'] ?? null,
            address2: $data['address_2'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? null,
            zipcode: $data['zipcode'] ?? null,
            country: (int) ($data['country'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'supplier_name' => $this->supplierName,
            'registration_no' => $this->registrationNo,
            'email' => $this->email,
            'contact_number' => $this->contactNumber,
            'website_url' => $this->websiteUrl,
            'address_1' => $this->address1,
            'address_2' => $this->address2,
            'city' => $this->city,
            'state' => $this->state,
            'zipcode' => $this->zipcode,
            'country' => $this->country,
        ];
    }
}
