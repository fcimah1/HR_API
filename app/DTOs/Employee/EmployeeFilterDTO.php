<?php

namespace App\DTOs\Employee;

class EmployeeFilterDTO
{
    public function __construct(
        public ?string $search = null,
        public ?int $department_id = null,
        public ?int $designation_id = null,
        public ?int $branch_id = null,
        public ?int $hierarchy_level = null,
        public ?bool $is_active = null,
        public ?string $from_date = null,
        public ?string $to_date = null,
        public ?string $sort_by = null,
        public ?string $sort_direction = 'asc',
        public int $page = 1,
        public int $limit = 20
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            search: $data['search'] ?? null,
            department_id: isset($data['department_id']) ? (int) $data['department_id'] : null,
            designation_id: isset($data['designation_id']) ? (int) $data['designation_id'] : null,
            branch_id: isset($data['branch_id']) ? (int) $data['branch_id'] : null,
            hierarchy_level: isset($data['hierarchy_level']) ? (int) $data['hierarchy_level'] : null,
            is_active: isset($data['is_active']) ? (bool) $data['is_active'] : null,
            from_date: $data['from_date'] ?? null,
            to_date: $data['to_date'] ?? null,
            sort_by: $data['sort_by'] ?? null,
            sort_direction: $data['sort_direction'] ?? 'asc',
            page: isset($data['page']) ? (int) $data['page'] : 1,
            limit: isset($data['limit']) ? (int) $data['limit'] : 20
        );
    }

    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'department_id' => $this->department_id,
            'designation_id' => $this->designation_id,
            'branch_id' => $this->branch_id,
            'hierarchy_level' => $this->hierarchy_level,
            'is_active' => $this->is_active,
            'from_date' => $this->from_date,
            'to_date' => $this->to_date,
            'sort_by' => $this->sort_by,
            'sort_direction' => $this->sort_direction,
            'page' => $this->page,
            'limit' => $this->limit,
        ];
    }
}