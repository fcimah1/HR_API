<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'document_id' => $this->document_id,
            'document_title' => $this->document_title,
            'document_type' => $this->document_type,
            'document_file' => $this->document_file ? url('storage/' . $this->document_file) : null,
            'file_size' => $this->file_size,
            'uploaded_at' => $this->created_at,
        ];
    }
}
