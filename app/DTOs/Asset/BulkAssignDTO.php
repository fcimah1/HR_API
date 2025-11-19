<?php

namespace App\DTOs\Asset;

class BulkAssignDTO
{
    public function __construct(
        public readonly array $assetIds,
        public readonly int $employeeId,
        public readonly int $companyId
    ) {}

    public static function fromRequest(array $data): self
    {
        $assetIds = is_array($data['asset_ids']) ? $data['asset_ids'] : explode(',', $data['asset_ids']);
        $assetIds = array_map('intval', $assetIds);
        $assetIds = array_filter($assetIds, fn($id) => $id > 0);

        return new self(
            assetIds: $assetIds,
            employeeId: (int) $data['employee_id'],
            companyId: (int) $data['company_id']
        );
    }

    public function toArray(): array
    {
        return [
            'asset_ids' => $this->assetIds,
            'employee_id' => $this->employeeId,
            'company_id' => $this->companyId,
        ];
    }
}

