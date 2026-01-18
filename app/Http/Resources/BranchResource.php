<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch_name,
        ];
    }
}
