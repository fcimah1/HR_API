<?php

namespace App\Repository\Interface;

use App\DTOs\Resignation\CreateResignationDTO;
use App\DTOs\Resignation\ResignationFilterDTO;
use App\DTOs\Resignation\UpdateResignationDTO;
use App\Models\Resignation;
use App\Models\User;

interface ResignationRepositoryInterface
{
    /**
     * الحصول على قائمة الاستقالات مع التصفية والترقيم الصفحي
     */
    public function getPaginatedResignations(ResignationFilterDTO $filters, User $user): array;

    /**
     * الحصول على استقالة بواسطة المعرف
     */
    public function findResignationById(int $id, int $companyId): ?Resignation;

    /**
     * الحصول على استقالة للموظف
     */
    public function findResignationForEmployee(int $id, int $employeeId): ?Resignation;

    /**
     * إنشاء استقالة جديدة
     */
    public function createResignation(CreateResignationDTO $dto): Resignation;

    /**
     * تحديث استقالة
     */
    public function updateResignation(Resignation $resignation, UpdateResignationDTO $dto): Resignation;

    /**
     * حذف استقالة
     */
    public function deleteResignation(Resignation $resignation): bool;

    /**
     * الموافقة على استقالة
     */
    public function approveResignation(Resignation $resignation, int $approvedBy, ?string $remarks = null): Resignation;

    /**
     * رفض استقالة
     */
    public function rejectResignation(Resignation $resignation, int $rejectedBy, ?string $remarks = null): Resignation;
}
