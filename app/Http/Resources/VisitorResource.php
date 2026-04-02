<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->visitor_id,
            'visitor_name' => $this->visitor_name,
            'department' => [
                'id' => $this->department_id,
                'name' => $this->department?->department_name,
            ],
            'visit_details' => [
                'purpose' => $this->visit_purpose,
                'date' => $this->visit_date,
                'check_in' => $this->check_in,
                'check_out' => $this->check_out,
            ],
            'contact' => [
                'phone' => $this->phone,
                'email' => $this->email,
                'address' => $this->address,
            ],
            'description' => $this->description,
            'metadata' => [
                'created_by' => [
                    'id' => $this->created_by,
                    'name' => trim(($this->creator?->first_name ?? '') . ' ' . ($this->creator?->last_name ?? '')),
                ],
                'created_at' => $this->created_at,
            ]
        ];
    }
}
