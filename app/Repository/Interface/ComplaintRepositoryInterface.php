<?php

namespace App\Repository\Interface;

use App\DTOs\Complaint\CreateComplaintDTO;
use App\DTOs\Complaint\ComplaintFilterDTO;
use App\DTOs\Complaint\UpdateComplaintDTO;
use App\Models\Complaint;
use App\Models\User;

interface ComplaintRepositoryInterface
{
    /**
     * الحصول على قائمة الشكاوى مع التصفية والترقيم الصفحي
     */
    public function getPaginatedComplaints(ComplaintFilterDTO $filters, User $user): array;

    /**
     * الحصول على شكوى بواسطة المعرف
     */
    public function findComplaintById(int $id, int $companyId): ?Complaint;

    /**
     * الحصول على شكوى للموظف
     */
    public function findComplaintForEmployee(int $id, int $employeeId): ?Complaint;

    /**
     * إنشاء شكوى جديدة
     */
    public function createComplaint(CreateComplaintDTO $dto): Complaint;

    /**
     * تحديث شكوى
     */
    public function updateComplaint(Complaint $complaint, UpdateComplaintDTO $dto): Complaint;

    /**
     * حذف شكوى
     */
    public function deleteComplaint(Complaint $complaint, int $rejectedBy, ?string $description = null): bool;

    /**
     * حل الشكوى
     */
    public function resolveComplaint(Complaint $complaint, int $resolvedBy, ?string $description = null): Complaint;

    /**
     * رفض الشكوى
     */
    public function rejectComplaint(Complaint $complaint, int $rejectedBy, ?string $remarks = null): Complaint;
}
