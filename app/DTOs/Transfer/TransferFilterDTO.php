<?php

namespace App\DTOs\Transfer;

use App\Enums\NumericalStatusEnum;
use Spatie\LaravelData\Data;

class TransferFilterDTO extends Data
{
    public function __construct(
        public readonly ?int $companyId = null,
        public readonly ?int $employeeId = null,
        public readonly ?array $employeeIds = null,
        public readonly ?int $status = null,
        public readonly ?int $departmentId = null,
        public readonly ?string $transferType = null,
        public readonly ?string $search = null,
        public readonly ?string $fromDate = null,
        public readonly ?string $toDate = null,
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {}

    public static function fromRequest(array $data): self
    {
        // Handle status conversion properly
        $status = null;
        // status can be string (pending/approved/rejected) or int (0/1/2)
        if (array_key_exists('status', $data) && $data['status'] !== null && $data['status'] !== '') {
            $inputStatus = is_numeric($data['status']) ? (int) $data['status'] : $data['status'];

            if ($inputStatus === 'approved' || $inputStatus === NumericalStatusEnum::APPROVED->value) {
                $status = NumericalStatusEnum::APPROVED->value;
            } else if ($inputStatus === 'rejected' || $inputStatus === NumericalStatusEnum::REJECTED->value) {
                $status = NumericalStatusEnum::REJECTED->value;
            } else if ($inputStatus === 'pending' || $inputStatus === NumericalStatusEnum::PENDING->value) {
                $status = NumericalStatusEnum::PENDING->value;
            } else {
                $status = null;
            }
        }
        return new self(
            companyId: $data['company_id'] ?? null,
            employeeId: $data['employee_id'] ?? null,
            employeeIds: $data['employee_ids'] ?? null,
            status: $status,
            departmentId: isset($data['department_id']) ? (int)$data['department_id'] : null,
            transferType: $data['transfer_type'] ?? null,
            search: $data['search'] ?? null,
            fromDate: $data['from_date'] ?? null,
            toDate: $data['to_date'] ?? null,
            page: (int)($data['page'] ?? 1),
            perPage: (int)($data['per_page'] ?? 15),
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'employee_ids' => $this->employeeIds,
            'status' => $this->status,
            'department_id' => $this->departmentId,
            'transfer_type' => $this->transferType,
            'search' => $this->search,
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
            'page' => $this->page,
            'per_page' => $this->perPage,
        ];
    }
}
