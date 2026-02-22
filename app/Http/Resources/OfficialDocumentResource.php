<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="OfficialDocumentResource",
 *     title="Official Document Resource",
 *     description="مورد المستندات الرسمية",
 *     @OA\Property(property="document_id", type="integer", example=1),
 *     @OA\Property(property="company_id", type="integer", example=24),
 *     @OA\Property(property="license_name", type="string", example="علي"),
 *     @OA\Property(property="document_type", type="string", example="قسيمة"),
 *     @OA\Property(property="license_no", type="string", example="1"),
 *     @OA\Property(property="expiry_date", type="string", example="2026-02-28"),
 *     @OA\Property(property="document_file", type="string", example="123456.png"),
 *     @OA\Property(property="document_url", type="string", example="http://example.com/uploads/123456.png"),
 *     @OA\Property(property="created_at", type="string", example="2026-02-22 12:14:08")
 * )
 */
class OfficialDocumentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'document_id' => $this->document_id,
            'company_id' => $this->company_id,
            'license_name' => $this->license_name,
            'document_type' => $this->document_type,
            'license_no' => $this->license_no,
            'expiry_date' => $this->expiry_date,
            'document_file' => $this->document_file,
            'document_url' => $this->document_file ? env('SHARED_UPLOADS_URL') . '/official_documents/' . $this->document_file : null,
            'created_at' => $this->created_at,
        ];
    }
}
