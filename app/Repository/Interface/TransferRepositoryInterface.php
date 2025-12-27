<?php

namespace App\Repository\Interface;

use App\DTOs\Transfer\CreateTransferDTO;
use App\DTOs\Transfer\TransferFilterDTO;
use App\DTOs\Transfer\UpdateTransferDTO;
use App\Models\Transfer;
use App\Models\User;

interface TransferRepositoryInterface
{
    /**
     * الحصول على قائمة النقل مع التصفية والترقيم الصفحي
     */
    public function getPaginatedTransfers(TransferFilterDTO $filters, User $user): array;

    /**
     * الحصول على نقل بواسطة المعرف
     */
    public function findTransferById(int $id, int $companyId): ?Transfer;

    /**
     * الحصول على نقل للموظف
     */
    public function findTransferForEmployee(int $id, int $employeeId): ?Transfer;

    /**
     * إنشاء نقل جديد
     */
    public function createTransfer(CreateTransferDTO $dto): Transfer;

    /**
     * تحديث نقل
     */
    public function updateTransfer(Transfer $transfer, UpdateTransferDTO $dto): Transfer;

    /**
     * حذف نقل
     */
    public function deleteTransfer(Transfer $transfer): bool;

    /**
     * الموافقة على نقل
     */
    public function approveTransfer(Transfer $transfer, int $approvedBy, ?string $remarks = null): Transfer;

    /**
     * رفض نقل
     */
    public function rejectTransfer(Transfer $transfer, int $rejectedBy, ?string $remarks = null): Transfer;

    /**
     * الحصول على طلبات الإجازة النشطة للموظف
     */
    public function getActiveLeaves(int $employeeId): array;

    /**
     * الحصول على السلف النشطة للموظف
     */
    public function getActiveAdvances(int $employeeId): array;

    /**
     * الحصول على العهد غير المرجعة للموظف
     */
    public function getUnreturnedCustody(int $employeeId): array;

    /**
     * تنفيذ النقل - تحديث بيانات الموظف
     */
    public function executeTransfer(Transfer $transfer): void;

    /**
     * الحصول على الشركات مع الفروع للنقل بين الشركات
     */
    public function getCompaniesWithBranches(): array;

    /**
     * البحث عن طلب نقل معلق للموظف
     */
    public function findPendingTransferForEmployee(int $employeeId): ?Transfer;
}
