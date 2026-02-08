<?php

namespace App\Repository\Interface;

use App\DTOs\CustodyClearance\CustodyFilterDTO;
use App\DTOs\CustodyClearance\CustodyClearanceFilterDTO;
use App\DTOs\CustodyClearance\CreateCustodyClearanceDTO;
use App\Models\CustodyClearance;
use App\Models\User;

interface CustodyClearanceRepositoryInterface
{
    /**
     * الحصول على العهد/الأصول للموظف
     */
    public function getCustodiesForEmployee(CustodyFilterDTO $filters): mixed;

    /**
     * الحصول على قائمة طلبات الإخلاء مع التصفية والترقيم
     */
    public function getPaginatedClearances(CustodyClearanceFilterDTO $filters, User $user): mixed;

    /**
     * الحصول على طلب إخلاء بواسطة المعرف
     */
    public function findClearanceById(int $id, int $companyId): ?CustodyClearance;

    /**
     * إنشاء طلب إخلاء جديد
     */
    public function createClearance(CreateCustodyClearanceDTO $dto): CustodyClearance;

    /**
     * إضافة عناصر للإخلاء
     */
    public function addClearanceItems(int $clearanceId, array $assetIds): void;

    /**
     * الموافقة على طلب إخلاء
     */
    public function approveClearance(CustodyClearance $clearance, int $approvedBy, ?string $remarks = null): CustodyClearance;

    /**
     * رفض طلب إخلاء
     */
    public function rejectClearance(CustodyClearance $clearance, int $rejectedBy, ?string $remarks = null): CustodyClearance;

    /**
     * إلغاء طلب إخلاء
     */
    public function cancelClearance(CustodyClearance $clearance): bool;

    /**
     * الحصول على جميع العهد للموظف
     */
    public function getAllCustodiesForEmployee(int $employeeId): array;
}
