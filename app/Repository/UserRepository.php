<?php

namespace App\Repository;

use App\Models\User;
use App\Models\UserDetails;
use App\Repository\Interface\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly User $model
    ) {}

    /**
     * Get all subordinate employee IDs using recursive reporting structure
     * Filters by same department and lower hierarchy levels
     * 
     * @param int $managerId
     * @return array
     */
    public function getSubordinateEmployeeIds(int $managerId): array
    {
        // Get manager's department and hierarchy level
        $manager = DB::table('ci_erp_users_details')
            ->join('ci_designations', 'ci_erp_users_details.designation_id', '=', 'ci_designations.designation_id')
            ->where('ci_erp_users_details.user_id', $managerId)
            ->first(['ci_erp_users_details.department_id', 'ci_designations.hierarchy_level']);

        if (!$manager) {
            return [];
        }

        // Get employees in same department with lower hierarchy levels
        $subordinates = DB::table('ci_erp_users_details')
            ->join('ci_designations', 'ci_erp_users_details.designation_id', '=', 'ci_designations.designation_id')
            ->join('ci_erp_users', 'ci_erp_users_details.user_id', '=', 'ci_erp_users.user_id')
            ->where('ci_erp_users_details.department_id', $manager->department_id)
            ->where('ci_designations.hierarchy_level', '>', $manager->hierarchy_level)
            ->where('ci_designations.hierarchy_level', '!=', 1) // Exclude highest level if needed
            ->where('ci_erp_users.is_active', 1)
            ->pluck('ci_erp_users_details.user_id')
            ->toArray();

        return array_map('intval', $subordinates);
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
