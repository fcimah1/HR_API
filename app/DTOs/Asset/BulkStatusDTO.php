<?php

namespace App\DTOs\Asset;

class BulkStatusDTO
{
    public function __construct(
        public readonly array $assetIds,
        public readonly bool $isWorking,
        public readonly int $companyId
    ) {}

    public static function fromRequest(array $data): self
    {
        $assetIds = is_array($data['asset_ids']) ? $data['asset_ids'] : explode(',', $data['asset_ids']);
        $assetIds = array_map('intval', $assetIds);
        $assetIds = array_filter($assetIds, fn($id) => $id > 0);

        return new self(
            assetIds: $assetIds,
            isWorking: (bool) $data['is_working'],
            companyId: (int) $data['company_id']
        );
    }

    public function toArray(): array
    {
        return [
            'asset_ids' => $this->assetIds,
            'is_working' => $this->isWorking,
            'company_id' => $this->companyId,
        ];
    }
}

