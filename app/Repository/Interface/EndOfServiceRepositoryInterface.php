<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\DTOs\EndOfService\EndOfServiceFilterDTO;
use App\DTOs\EndOfService\CreateEndOfServiceDTO;
use App\DTOs\EndOfService\UpdateEndOfServiceDTO;
use App\Models\EndOfService;

interface EndOfServiceRepositoryInterface
{
    /**
     * الحصول على جميع الحسابات مع الفلترة والـ Pagination
     */
    public function getAll(EndOfServiceFilterDTO $filters): mixed;

    /**
     * الحصول على حساب بالـ ID
     */
    public function getById(int $id, int $companyId): ?EndOfService;

    /**
     * إنشاء حساب جديد
     */
    public function create(CreateEndOfServiceDTO $dto): EndOfService;

    /**
     * تحديث حساب
     */
    public function update(EndOfService $model, UpdateEndOfServiceDTO $dto): EndOfService;

    /**
     * حذف حساب
     */
    public function delete(int $id, int $companyId): bool;

    /**
     * البحث عن حساب معلق (غير معتمد) لموظف
     */
    public function findPendingByEmployeeId(int $employeeId, int $companyId): ?EndOfService;

    /**
     * تحديث حساب ببيانات جديدة (Re-calculate)
     */
    public function updateCalculation(EndOfService $model, CreateEndOfServiceDTO $dto): EndOfService;
}
