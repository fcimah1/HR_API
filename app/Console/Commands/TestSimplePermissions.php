<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\StaffRole;
use App\Services\SimplePermissionService;

class TestSimplePermissions extends Command
{
    protected $signature = 'test:simple-permissions {user_id?}';
    protected $description = 'Test the simple permission system';

    private SimplePermissionService $permissionService;

    public function __construct(SimplePermissionService $permissionService)
    {
        parent::__construct();
        $this->permissionService = $permissionService;
    }

    public function handle()
    {
        $this->info('🧪 اختبار نظام الصلاحيات المبسط');
        $this->info('===============================');

        $userId = $this->argument('user_id');
        
        if ($userId) {
            $this->testSpecificUser($userId);
        } else {
            $this->testSystemOverview();
        }

        return 0;
    }

    private function testSpecificUser(int $userId): void
    {
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("❌ المستخدم غير موجود: {$userId}");
            return;
        }

        $this->info("\n👤 اختبار المستخدم: {$user->first_name} {$user->last_name}");
        $this->info("=====================================");
        $this->info("🆔 معرف المستخدم: {$user->user_id}");
        $this->info("🏢 معرف الشركة: {$user->company_id}");
        $this->info("🎭 معرف الدور: {$user->user_role_id}");
        $this->info("📧 البريد الإلكتروني: {$user->email}");

        // تحديد نوع المستخدم
        if ($this->permissionService->isCompanyOwner($user)) {
            $this->info("👑 النوع: صاحب الشركة");
            $this->info("🔑 الصلاحيات: كاملة (*)");
        } elseif ($this->permissionService->isEmployee($user)) {
            $this->info("👤 النوع: موظف");
            $this->testEmployeePermissions($user);
        } else {
            $this->warn("⚠️ النوع: غير محدد");
        }

        // اختبار معرف الشركة الفعلي
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
        $this->info("🏢 معرف الشركة الفعلي: {$effectiveCompanyId}");

        // اختبار صلاحيات الإجازات
        $this->testLeavePermissions($user);
    }

    private function testEmployeePermissions(User $user): void
    {
        $role = StaffRole::where('role_id', $user->user_role_id)
            ->where('company_id', $user->company_id)
            ->first();

        if ($role) {
            $this->info("🎭 اسم الدور: {$role->role_name}");
            $this->info("🔑 مستوى الوصول: {$role->role_access}");
            
            $permissions = $this->permissionService->getUserPermissions($user);
            $this->info("📊 عدد الصلاحيات: " . count($permissions));
            
            if (count($permissions) <= 10) {
                $this->info("📋 الصلاحيات:");
                foreach ($permissions as $permission) {
                    $this->info("  • {$permission}");
                }
            } else {
                $this->info("📋 أول 10 صلاحيات:");
                for ($i = 0; $i < 10; $i++) {
                    if (isset($permissions[$i])) {
                        $this->info("  • {$permissions[$i]}");
                    }
                }
                $this->info("  ... و " . (count($permissions) - 10) . " صلاحية أخرى");
            }
        } else {
            $this->error("❌ الدور غير موجود");
        }
    }

    private function testLeavePermissions(User $user): void
    {
        $this->info("\n🔐 اختبار صلاحيات الإجازات:");
        $this->info("==================");

        $leavePermissions = [
            'leave.create' => 'إنشاء طلب إجازة',
            'leave.view.own' => 'عرض الطلبات الشخصية',
            'leave.view.all' => 'عرض جميع الطلبات',
            'leave.update' => 'تعديل الطلبات',
            'leave.approve' => 'الموافقة على الطلبات',
        ];

        foreach ($leavePermissions as $permission => $description) {
            $hasPermission = $this->permissionService->checkPermission($user, $permission);
            $status = $hasPermission ? '✅' : '❌';
            $this->info("  {$status} {$description} ({$permission})");
        }
    }

    private function testSystemOverview(): void
    {
        $this->info("\n📊 نظرة عامة على النظام:");
        $this->info("========================");

        // إحصائيات المستخدمين
        $totalUsers = User::count();
        $companyOwners = User::where('company_id', 0)->where('user_role_id', 0)->count();
        $employees = User::where('company_id', '>', 0)->where('user_role_id', '>', 0)->count();
        $others = $totalUsers - $companyOwners - $employees;

        $this->info("👥 إجمالي المستخدمين: {$totalUsers}");
        $this->info("👑 أصحاب الشركات: {$companyOwners}");
        $this->info("👤 الموظفين: {$employees}");
        $this->info("❓ أخرى: {$others}");

        // إحصائيات الأدوار
        $totalRoles = StaffRole::count();
        $this->info("🎭 إجمالي الأدوار: {$totalRoles}");

        // أكبر الشركات
        $this->info("\n🏢 أكبر 5 شركات:");
        $companies = User::where('company_id', 0)
            ->get()
            ->map(function($company) {
                $employeeCount = User::where('company_id', $company->user_id)->count();
                return [
                    'name' => $company->first_name . ' ' . $company->last_name,
                    'count' => $employeeCount
                ];
            })
            ->sortByDesc('count')
            ->take(5);

        foreach ($companies as $company) {
            $this->info("  • {$company['name']}: {$company['count']} موظف");
        }

        // اختبار عينة من المستخدمين
        $this->info("\n🧪 اختبار عينة من المستخدمين:");
        $sampleUsers = User::where('user_role_id', '>', 0)->take(3)->get();
        
        foreach ($sampleUsers as $user) {
            $hasLeaveCreate = $this->permissionService->checkPermission($user, 'leave.create');
            $status = $hasLeaveCreate ? '✅' : '❌';
            $this->info("  {$status} {$user->first_name} {$user->last_name} - {$user->company_name}");
        }
    }
}
