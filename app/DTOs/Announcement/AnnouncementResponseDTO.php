<?php

namespace App\DTOs\Announcement;

use App\Models\Announcement;

/**
 * @OA\Schema(
 *     title="AnnouncementResponseDTO",
 *     description="تنسيق بيانات الإعلان في الرد"
 * )
 */
class AnnouncementResponseDTO
{
    /**
     * @OA\Property(property="id", type="integer", description="معرف الإعلان"),
     * @OA\Property(property="company_id", type="integer", description="معرف الشركة"),
     * @OA\Property(property="department_id", type="string", description="معرفات الأقسام المستهدفة"),
     * @OA\Property(property="audience_id", type="string", description="معرفات الموظفين المستهدفين"),
     * @OA\Property(property="title", type="string", description="عنوان الإعلان"),
     * @OA\Property(property="start_date", type="string", description="تاريخ البدء"),
     * @OA\Property(property="end_date", type="string", description="تاريخ الانتهاء"),
     * @OA\Property(property="published_by", type="integer", description="معرف الموظف الذي قام بالنشر"),
     * @OA\Property(property="publisher_name", type="string", description="اسم الموظف الذي قام بالنشر"),
     * @OA\Property(property="summary", type="string", description="الملخص"),
     * @OA\Property(property="description", type="string", description="الوصف الكامل"),
     * @OA\Property(property="is_active", type="boolean", description="حالة النشاط"),
     * @OA\Property(property="created_at", type="string", description="تاريخ الإنشاء")
     */
    public function __construct(
        public readonly int $id,
        public readonly int $companyId,
        public readonly string $departmentId,
        public readonly ?string $audienceId,
        public readonly string $title,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly ?int $publishedBy,
        public readonly ?string $publisherName,
        public readonly string $summary,
        public readonly string $description,
        public readonly bool $isActive,
        public readonly string $createdAt,
    ) {}

    public static function fromModel(Announcement $announcement): self
    {
        return new self(
            id: $announcement->announcement_id,
            companyId: (int) $announcement->company_id,
            departmentId: $announcement->department_id,
            audienceId: $announcement->audience_id,
            title: $announcement->title,
            startDate: $announcement->start_date,
            endDate: $announcement->end_date,
            publishedBy: $announcement->published_by ? (int) $announcement->published_by : null,
            publisherName: $announcement->publisher ? $announcement->publisher->first_name . ' ' . $announcement->publisher->last_name : null,
            summary: $announcement->summary,
            description: $announcement->description,
            isActive: (bool) $announcement->is_active,
            createdAt: $announcement->created_at,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->companyId,
            'department_id' => $this->departmentId,
            'audience_id' => $this->audienceId,
            'title' => $this->title,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'published_by' => $this->publishedBy,
            'publisher_name' => $this->publisherName,
            'summary' => $this->summary,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt,
        ];
    }
}
