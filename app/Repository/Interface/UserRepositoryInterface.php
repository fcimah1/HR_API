<?php

namespace App\Repository\Interface;

use App\Models\User;
use App\Models\UserDetails;

interface UserRepositoryInterface
{
    /**
     * Get all subordinate employee IDs using recursive reporting structure
     * Filters by same department and lower hierarchy levels
     * 
     * @param User $manager
     * @return array
     */
    public function getSubordinateEmployeeIds(User $manager);

    public function getUserByCompositeKey(int $companyId, int $branchId, string $kioskCode): ?UserDetails;

    /**
     * البحث عن عدة موظفين باستخدام kiosk_codes دفعة واحدة
     * 
     * @param int $companyId رقم الشركة
     * @param int $branchId رقم الفرع
     * @param array $kioskCodes مصفوفة أكواد الكشك
     * @return array [kiosk_code => user_id] mapping
     */
    public function getUsersByKioskCodes(int $companyId, int $branchId, array $kioskCodes): array;
}
