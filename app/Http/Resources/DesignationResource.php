<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'designation_id' => $this->designation_id,
            'designation_name' => $this->designation_name,
            'hierarchy_level' => $this->hierarchy_level ?? 5,
            'department_id' => $this->department_id,
            'department_name' => $this->department->department_name ?? null,
            'employees_count' => $this->user_details_count ?? 0,
            'created_at' => $this->created_at instanceof \Carbon\Carbon ? $this->created_at->toDateTimeString() : $this->created_at,
        ];
    }
}
