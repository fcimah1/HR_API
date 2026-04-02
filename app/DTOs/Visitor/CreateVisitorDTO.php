<?php

declare(strict_types=1);

namespace App\DTOs\Visitor;

class CreateVisitorDTO
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $departmentId,
        public readonly ?string $purpose,
        public readonly string $name,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly string $date,
        public readonly string $checkIn,
        public readonly ?string $checkOut,
        public readonly ?string $address,
        public readonly ?string $description,
        public readonly int $createdBy
    ) {}

    public static function fromRequest(array $data, int $companyId, int $userId): self
    {
        return new self(
            companyId: $companyId,
            departmentId: (int)$data['department_id'],
            purpose: $data['visit_purpose'] ?? null,
            name: $data['visitor_name'],
            phone: $data['phone'] ?? null,
            email: $data['email'] ?? null,
            date: $data['visit_date'],
            checkIn: $data['check_in'],
            checkOut: $data['check_out'] ?? null,
            address: $data['address'] ?? null,
            description: $data['description'] ?? null,
            createdBy: $userId
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
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
            'created_by' => $this->createdBy,
            'created_at' => date('d-m-Y H:i:s'),
        ];
    }
}
