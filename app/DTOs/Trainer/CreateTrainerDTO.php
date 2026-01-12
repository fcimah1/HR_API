<?php

declare(strict_types=1);

namespace App\DTOs\Trainer;

class CreateTrainerDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $contactNumber,
        public readonly string $email,
        public readonly ?string $expertise = null,
        public readonly ?string $address = null,
    ) {}

    public static function fromRequest(array $data, int $companyId): self
    {
        return new self(
            companyId: $companyId,
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            contactNumber: $data['contact_number'],
            email: $data['email'],
            expertise: $data['expertise'] ?? null,
            address: $data['address'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'contact_number' => $this->contactNumber,
            'email' => $this->email,
            'expertise' => $this->expertise,
            'address' => $this->address,
            'created_at' => now()->format('d-m-Y H:i:s'),
        ];
    }
}
