<?php

declare(strict_types=1);

namespace App\DTOs\Visitor;

class UpdateVisitorDTO
{
    public function __construct(
        public readonly ?int $departmentId = null,
        public readonly ?string $purpose = null,
        public readonly ?string $name = null,
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
        public readonly ?string $date = null,
        public readonly ?string $checkIn = null,
        public readonly ?string $checkOut = null,
        public readonly ?string $address = null,
        public readonly ?string $description = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            departmentId: isset($data['department_id']) ? (int)$data['department_id'] : null,
            purpose: $data['visit_purpose'] ?? null,
            name: $data['visitor_name'] ?? null,
            phone: $data['phone'] ?? null,
            email: $data['email'] ?? null,
            date: $data['visit_date'] ?? null,
            checkIn: $data['check_in'] ?? null,
            checkOut: $data['check_out'] ?? null,
            address: $data['address'] ?? null,
            description: $data['description'] ?? null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'department_id' => $this->departmentId,
            'visit_purpose' => $this->purpose,
            'visitor_name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'visit_date' => $this->date,
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'address' => $this->address,
            'description' => $this->description,
        ], fn($value) => $value !== null);
    }
}
