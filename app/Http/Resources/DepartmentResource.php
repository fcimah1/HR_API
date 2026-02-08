<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'department_id' => $this->department_id,
            'department_name' => $this->department_name,
            'department_head_id' => $this->department_head,
            'department_head_name' => $this->departmentHead ?
                $this->departmentHead->first_name . ' ' . $this->departmentHead->last_name : null,
            'employees_count' => $this->user_details_count ?? 0,
            'created_at' => $this->created_at instanceof \Carbon\Carbon ? $this->created_at->toDateTimeString() : $this->created_at,
        ];
    }
}
