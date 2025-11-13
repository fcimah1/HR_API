<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\ErpConstant;
use App\Services\SimplePermissionService;

class CheckLeaveTypes extends Command
{
    protected $signature = 'check:leave-types {user_id}';
    protected $description = 'Check available leave types for a user';

    public function handle()
    {
        $userId = $this->argument('user_id');
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("المستخدم غير موجود: {$userId}");
            return 1;
        }

        $permissionService = app(SimplePermissionService::class);
        $effectiveCompanyId = $permissionService->getEffectiveCompanyId($user);

        $this->info("👤 المستخدم: {$user->first_name} {$user->last_name}");
        $this->info("🏢 معرف الشركة: {$user->company_id}");
        $this->info("🏢 معرف الشركة الفعلي: {$effectiveCompanyId}");
        
        $this->info("\n📋 أنواع الإجازات المتاحة:");
        $this->info("========================");

        $leaveTypes = ErpConstant::where('type', ErpConstant::TYPE_LEAVE_TYPE)
            ->where(function($query) use ($effectiveCompanyId) {
                $query->where('company_id', $effectiveCompanyId)
                      ->orWhere('company_id', 0); // الأنواع العامة
            })
            ->where('field_three', '1') // نشط
            ->get();

        if ($leaveTypes->isEmpty()) {
            $this->warn("❌ لا توجد أنواع إجازات متاحة لهذا المستخدم");
        } else {
            foreach ($leaveTypes as $type) {
                $companyType = $type->company_id == 0 ? 'عام' : "شركة {$type->company_id}";
                $this->info("✅ ID: {$type->constants_id} | {$type->category_name} | ({$companyType})");
            }
        }

        // التحقق من أنواع الإجازات الأخرى في النظام
        $this->info("\n🔍 جميع أنواع الإجازات في النظام:");
        $this->info("================================");
        
        $allLeaveTypes = ErpConstant::where('type', ErpConstant::TYPE_LEAVE_TYPE)
            ->where('field_three', '1')
            ->get();

        foreach ($allLeaveTypes as $type) {
            $companyType = $type->company_id == 0 ? 'عام' : "شركة {$type->company_id}";
            $available = $type->company_id == $effectiveCompanyId || $type->company_id == 0 ? '✅' : '❌';
            $this->info("{$available} ID: {$type->constants_id} | {$type->category_name} | ({$companyType})");
        }

        return 0;
    }
}
