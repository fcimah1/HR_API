<?php

namespace App\Repository;

use App\Models\User;
use App\Models\UserDetails;
use App\Repository\Interface\UserRepositoryInterface;
use App\Services\SimplePermissionService;
use Illuminate\Support\Facades\DB;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly User $model,
        private readonly SimplePermissionService $permissionService
    ) {}

    /**
     * Get all subordinate employee IDs using recursive reporting structure
     * Filters by same department and lower hierarchy levels
     * 
     * @param User $manager
     * @return array
     */
    public function getSubordinateEmployeeIds(User $manager)
    {
        // الحصول على جميع الموظفين في نفس الشركة
        $allEmployees = User::where('company_id', $manager->company_id)
            ->where('user_type', 'staff')
            ->get();

        $subordinateIds = [];

        foreach ($allEmployees as $employee) {
            // التحقق إذا كان المدير يمكنه عرض طلبات هذا الموظف
            if ($this->permissionService->canViewEmployeeRequests($manager, $employee)) {
                $subordinateIds[] = $employee->user_id;
            }
        }

        return $subordinateIds;
    }

    /**
     * البحث عن الموظف باستخدام kiosk_code من جدول المستخدمين
     * Find employee by kiosk_code from users table
     * 
     * @param int $companyId رقم الشركة
     * @param int $branchId رقم الفرع
     * @param string $kioskCode كود الكشك/البصمة من جدول المستخدمين
     * @return UserDetails|null
     */
    public function getUserByCompositeKey(int $companyId, int $branchId, string $kioskCode): ?UserDetails
    {
        // محاولة البحث بالمفتاح المركب (company + branch + kiosk_code)
        $user = User::where('company_id', $companyId)
            ->where('kiosk_code', $kioskCode)
            ->where('is_active', 1)
            ->whereHas('user_details', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->first();

        if ($user) {
            return $user->user_details;
        }

        // fallback: ابحث عن موظف بنفس company و kiosk_code لكن branch = NULL أو 0
        $user = User::where('company_id', $companyId)
            ->where('kiosk_code', $kioskCode)
            ->where('is_active', 1)
            ->whereHas('user_details', function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('branch_id')
                        ->orWhere('branch_id', 0);
                });
            })
            ->first();

        return $user?->user_details;
    }

    /**
     * البحث عن عدة موظفين باستخدام kiosk_codes دفعة واحدة
     * Find multiple employees by kiosk_codes (bulk lookup)
     * 
     * @param int $companyId رقم الشركة
     * @param int $branchId رقم الفرع
     * @param array $kioskCodes مصفوفة أكواد الكشك
     * @return array [kiosk_code => user_id] mapping
     */
    public function getUsersByKioskCodes(int $companyId, int $branchId, array $kioskCodes): array
    {
        // البحث عن الموظفين باستخدام kiosk_code من جدول User مع branch_id من UserDetails
        $matchedRecords = User::where('company_id', $companyId)
            ->whereIn('kiosk_code', $kioskCodes)
            ->where('is_active', 1)
            ->whereHas('user_details', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->select('user_id', 'kiosk_code')
            ->get();

        // بناء الـ mapping
        $matchedEmployees = [];
        foreach ($matchedRecords as $record) {
            $matchedEmployees[$record->kiosk_code] = $record->user_id;
        }

        // إذا لم نجد جميع الموظفين، ابحث عن الموظفين بـ branch_id = NULL أو 0 (fallback)
        if (count($matchedEmployees) < count($kioskCodes)) {
            $foundCodes = array_keys($matchedEmployees);
            $notFoundCodes = array_diff($kioskCodes, $foundCodes);

            if (!empty($notFoundCodes)) {
                // البحث عن الموظفين بدون فرع (branch_id = null أو 0)
                $fallbackRecords = User::where('company_id', $companyId)
                    ->whereIn('kiosk_code', $notFoundCodes)
                    ->where('is_active', 1)
                    ->whereHas('user_details', function ($query) {
                        $query->where(function ($q) {
                            $q->whereNull('branch_id')
                                ->orWhere('branch_id', 0);
                        });
                    })
                    ->select('user_id', 'kiosk_code')
                    ->get();

                // إضافة للـ mapping
                foreach ($fallbackRecords as $record) {
                    $matchedEmployees[$record->kiosk_code] = $record->user_id;
                }
            }
        }

        return $matchedEmployees;
    }
}
