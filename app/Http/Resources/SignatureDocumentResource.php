<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SignatureDocumentResource",
 *     title="Signature Document Resource",
 *     description="مورد ملفات التوقيع الإلكتروني",
 *     @OA\Property(property="document_id", type="integer", example=1),
 *     @OA\Property(property="company_id", type="integer", example=24),
 *     @OA\Property(property="folder_id", type="integer", example=24),
 *     @OA\Property(property="share_with_employees", type="integer", example=1),
 *     @OA\Property(property="document_file", type="string", example="123456.pdf"),
 *     @OA\Property(property="document_name", type="string", example="عقد عمل"),
 *     @OA\Property(property="document_size", type="string", example="102400"),
 *     @OA\Property(property="signature_task", type="integer", example=1),
 *     @OA\Property(property="document_url", type="string", example="http://example.com/uploads/signature_documents/123456.pdf"),
 *     @OA\Property(property="created_at", type="string", example="2026-02-22 12:47:15")
 * )
 */
class SignatureDocumentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'document_id' => $this->document_id,
            'company_id' => $this->company_id,
            'folder_id' => $this->folder_id,
            'share_with_employees' => $this->share_with_employees,
            'document_file' => $this->document_file,
            'document_name' => $this->document_name,
            'document_size' => $this->document_size,
            'signature_task' => $this->signature_task,
            'signature_task_label' => \App\Enums\SignatureTaskEnum::tryTranslate($this->signature_task),
            'document_url' => $this->document_file ? env('SHARED_UPLOADS_URL') . '/pdf_files/files/' . $this->document_file : null,
            'created_at' => $this->created_at,
            'assigned_staff' => $this->assignedStaff ? $this->assignedStaff->map(function ($staff) {
                return [
                    'staff_id' => $staff->staff_id,
                    'staff_name' => $staff->employee ? $staff->employee->full_name : 'Unknown',
                ];
            }) : [],
        ];
    }
}
