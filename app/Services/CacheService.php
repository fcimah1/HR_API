<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\ErpConstant;
use App\Models\Holiday;
use App\Models\OfficeShift;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * خدمة مركزية للتعامل مع الـ Cache
 * Centralized Cache Service for static data
 */
class CacheService
{
    // Cache TTL بالثواني
    private const TTL_DAY = 86400;      // 24 ساعة
    private const TTL_HALF_DAY = 43200; // 12 ساعة
    private const TTL_HOUR = 3600;      // 1 ساعة
    private const TTL_WEEK = 604800;    // 7 أيام

    // ===== Designations =====

    /**
     * جلب المسميات الوظيفية للشركة
     */
    public function getDesignations(int $companyId): Collection
    {
        $cacheKey = "designations.company.{$companyId}";

        return Cache::remember($cacheKey, self::TTL_DAY, function () use ($companyId) {
            return Designation::forCompany($companyId)->get();
        });
    }

    /**
     * جلب مسمى وظيفي محدد
     */
    public function getDesignation(int $designationId): ?Designation
    {
        $cacheKey = "designation.{$designationId}";

        return Cache::remember($cacheKey, self::TTL_DAY, function () use ($designationId) {
            return Designation::find($designationId);
        });
    }

    /**
     * مسح cache المسميات الوظيفية للشركة
     */
    public function clearDesignationsCache(int $companyId): void
    {
        Cache::forget("designations.company.{$companyId}");
        Log::debug("Designations cache cleared for company {$companyId}");
    }

    /**
     * مسح cache مسمى وظيفي محدد
     */
    public function clearDesignationCache(int $designationId, ?int $companyId = null): void
    {
        Cache::forget("designation.{$designationId}");
        if ($companyId) {
            $this->clearDesignationsCache($companyId);
        }
    }

    // ===== Departments =====

    /**
     * جلب الأقسام للشركة
     */
    public function getDepartments(int $companyId): Collection
    {
        $cacheKey = "departments.company.{$companyId}";

        return Cache::remember($cacheKey, self::TTL_DAY, function () use ($companyId) {
            return Department::forCompany($companyId)->get();
        });
    }

    /**
     * جلب قسم محدد
     */
    public function getDepartment(int $departmentId): ?Department
    {
        $cacheKey = "department.{$departmentId}";

        return Cache::remember($cacheKey, self::TTL_DAY, function () use ($departmentId) {
            return Department::find($departmentId);
        });
    }

    /**
     * مسح cache الأقسام للشركة
     */
    public function clearDepartmentsCache(int $companyId): void
    {
        Cache::forget("departments.company.{$companyId}");
        Log::debug("Departments cache cleared for company {$companyId}");
    }

    // ===== Branches =====

    /**
     * جلب الفروع للشركة
     */
    public function getBranches(int $companyId): Collection
    {
        $cacheKey = "branches.company.{$companyId}";

        return Cache::remember($cacheKey, self::TTL_DAY, function () use ($companyId) {
            return Branch::forCompany($companyId)->get();
        });
    }

    /**
     * جلب فرع محدد
     */
    public function getBranch(int $branchId): ?Branch
    {
        $cacheKey = "branch.{$branchId}";

        return Cache::remember($cacheKey, self::TTL_DAY, function () use ($branchId) {
            return Branch::find($branchId);
        });
    }

    /**
     * مسح cache الفروع للشركة
     */
    public function clearBranchesCache(int $companyId): void
    {
        Cache::forget("branches.company.{$companyId}");
        Log::debug("Branches cache cleared for company {$companyId}");
    }

    // ===== Leave Types =====

    /**
     * جلب أنواع الإجازات للشركة
     */
    public function getLeaveTypes(int $companyId): array
    {
        $cacheKey = "leave_types.company.{$companyId}";

        return Cache::remember($cacheKey, self::TTL_DAY, function () use ($companyId) {
            return ErpConstant::where('type', 'leave_type')
                ->when($companyId, function ($query) use ($companyId) {
                    $query->where('company_id', $companyId);
                })
                ->select(['constants_id', 'company_id', 'type', 'category_name'])
                ->get()
                ->map(function ($item) {
                    return [
                        'leave_type_id' => $item->constants_id,
                        'leave_type_name' => $item->category_name,
                        'company_id' => $item->company_id,
                    ];
                })
                ->toArray();
        });
    }

    /**
     * مسح cache أنواع الإجازات
     */
    public function clearLeaveTypesCache(int $companyId): void
    {
        Cache::forget("leave_types.company.{$companyId}");
        Log::debug("Leave types cache cleared for company {$companyId}");
    }

    // ===== Holidays =====

    /**
     * جلب العطلات للشركة للسنة
     */
    public function getHolidays(int $companyId, ?int $year = null): Collection
    {
        $year = $year ?? now()->year;
        $cacheKey = "holidays.company.{$companyId}.year.{$year}";

        return Cache::remember($cacheKey, self::TTL_DAY, function () use ($companyId, $year) {
            return Holiday::where('company_id', $companyId)
                ->whereYear('event_start_date', $year)
                ->orWhereYear('event_end_date', $year)
                ->get();
        });
    }

    /**
     * مسح cache العطلات
     */
    public function clearHolidaysCache(int $companyId, ?int $year = null): void
    {
        $year = $year ?? now()->year;
        Cache::forget("holidays.company.{$companyId}.year.{$year}");
        Log::debug("Holidays cache cleared for company {$companyId} year {$year}");
    }

    // ===== Office Shifts =====

    /**
     * جلب الشيفتات للشركة
     */
    public function getOfficeShifts(int $companyId): Collection
    {
        $cacheKey = "office_shifts.company.{$companyId}";

        return Cache::remember($cacheKey, self::TTL_HALF_DAY, function () use ($companyId) {
            return OfficeShift::where('company_id', $companyId)->get();
        });
    }

    /**
     * جلب شيفت محدد
     */
    public function getOfficeShift(int $shiftId): ?OfficeShift
    {
        $cacheKey = "office_shift.{$shiftId}";

        return Cache::remember($cacheKey, self::TTL_HALF_DAY, function () use ($shiftId) {
            return OfficeShift::find($shiftId);
        });
    }

    /**
     * مسح cache الشيفتات
     */
    public function clearOfficeShiftsCache(int $companyId): void
    {
        Cache::forget("office_shifts.company.{$companyId}");
        Log::debug("Office shifts cache cleared for company {$companyId}");
    }

    // ===== User Permissions =====

    /**
     * جلب صلاحيات المستخدم
     */
    public function getUserPermissions(int $userId): array
    {
        $cacheKey = "user_permissions.{$userId}";

        return Cache::remember($cacheKey, self::TTL_HOUR, function () use ($userId) {
            $user = \App\Models\User::find($userId);
            return $user ? $user->getUserPermissions() : [];
        });
    }

    /**
     * مسح cache صلاحيات المستخدم
     */
    public function clearUserPermissionsCache(int $userId): void
    {
        Cache::forget("user_permissions.{$userId}");
        Log::debug("User permissions cache cleared for user {$userId}");
    }

    // ===== مسح كل الـ Cache للشركة =====

    /**
     * مسح كل cache الشركة
     */
    public function clearAllCompanyCache(int $companyId): void
    {
        $this->clearDesignationsCache($companyId);
        $this->clearDepartmentsCache($companyId);
        $this->clearBranchesCache($companyId);
        $this->clearLeaveTypesCache($companyId);
        $this->clearHolidaysCache($companyId);
        $this->clearOfficeShiftsCache($companyId);
        $this->clearEmployeesCache($companyId);

        Log::info("All cache cleared for company {$companyId}");
    }

    // ===== Employees for Dropdown =====

    /**
     * جلب الموظفين للـ Dropdown (الاسم والمعرف فقط)
     */
    public function getEmployeesForDropdown(int $companyId): Collection
    {
        $cacheKey = "employees_dropdown.company.{$companyId}";

        return Cache::remember($cacheKey, self::TTL_HOUR, function () use ($companyId) {
            return \App\Models\User::where('ci_erp_users.company_id', $companyId)
                ->where('user_type', 'staff')
                ->leftJoin('ci_erp_users_details', 'ci_erp_users.user_id', '=', 'ci_erp_users_details.user_id')
                ->select(
                    'ci_erp_users.user_id',
                    'ci_erp_users.first_name',
                    'ci_erp_users.last_name',
                    'ci_erp_users_details.employee_id'
                )
                ->selectRaw("CONCAT(ci_erp_users.first_name, ' ', ci_erp_users.last_name) as full_name")
                ->orderBy('ci_erp_users.first_name')
                ->get();
        });
    }

    /**
     * مسح cache الموظفين
     */
    public function clearEmployeesCache(int $companyId): void
    {
        Cache::forget("employees_dropdown.company.{$companyId}");
        Log::debug("Employees dropdown cache cleared for company {$companyId}");
    }

    // ===== ERP Constants =====

    /**
     * جلب الإعدادات العامة للشركة
     */
    public function getErpConstants(int $companyId, ?string $category = null): Collection
    {
        $cacheKey = $category
            ? "erp_constants.company.{$companyId}.{$category}"
            : "erp_constants.company.{$companyId}";

        return Cache::remember($cacheKey, self::TTL_DAY, function () use ($companyId, $category) {
            $query = ErpConstant::where('company_id', $companyId);

            if ($category) {
                $query->where('category', $category);
            }

            return $query->get();
        });
    }

    /**
     * مسح cache الإعدادات العامة
     */
    public function clearErpConstantsCache(int $companyId, ?string $category = null): void
    {
        if ($category) {
            Cache::forget("erp_constants.company.{$companyId}.{$category}");
        }
        Cache::forget("erp_constants.company.{$companyId}");
        Log::debug("ERP constants cache cleared for company {$companyId}");
    }
}
