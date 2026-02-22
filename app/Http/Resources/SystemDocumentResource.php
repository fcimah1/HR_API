<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SystemDocumentResource",
 *     title="System Document Resource",
 *     description="مورد المستندات العامة",
 *     @OA\Property(property="document_id", type="integer", example=1),
 *     @OA\Property(property="company_id", type="integer", example=24),
 *     @OA\Property(property="department_id", type="integer", example=10),
 *     @OA\Property(property="department_name", type="string", example="المالية"),
 *     @OA\Property(property="document_name", type="string", example="عقد عمل"),
 *     @OA\Property(property="document_type", type="string", example="pdf"),
 *     @OA\Property(property="document_file", type="string", example="123456.pdf"),
 *     @OA\Property(property="document_url", type="string", example="http://example.com/uploads/123456.pdf"),
 *     @OA\Property(property="created_at", type="string", example="2026-02-22 10:00:00")
 * )
 */
class SystemDocumentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'document_id' => $this->document_id,
            'company_id' => $this->company_id,
            'department_id' => $this->department_id,
            'department_name' => $this->department?->department_name,
            'document_name' => $this->document_name,
            'document_type' => $this->document_type,
            'document_file' => $this->document_file,
            'document_url' => $this->document_file ? env('SHARED_UPLOADS_URL') . '/system_documents/' . $this->document_file : null,
            'created_at' => $this->created_at,
        ];
    }
}
