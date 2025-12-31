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

    public function getUserByCompositeKey(int $companyId, int $branchId, string $employeeId): ?UserDetails
    {
        // محاولة البحث بالمفتاح المركب (company + branch + employee)
        $userDetails = UserDetails::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('employee_id', $employeeId)
            ->first();

        if ($userDetails) {
            return $userDetails;
        }

        // fallback: ابحث عن موظف بنفس company و employee لكن branch = NULL أو 0
        return UserDetails::where('company_id', $companyId)
            ->whereIn('branch_id', [0, null])
            ->where('employee_id', $employeeId)
            ->first();
    }


    

}
