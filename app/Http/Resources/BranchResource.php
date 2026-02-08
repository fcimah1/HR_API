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
            'coordinates' => $this->formatted_coordinates,
            'employees_count' => $this->user_details_count ?? 0,
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
        ];
    }
}
