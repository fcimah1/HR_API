<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\SimplePermissionService;

class SimpleCompanyIsolation
{
    private SimplePermissionService $permissionService;

    public function __construct(SimplePermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'غير مصرح - يجب تسجيل الدخول'], 401);
        }

        // إضافة معلومات الشركة الفعلية للـ request بدون استبدال البيانات الموجودة
        $effectiveCompanyId = $this->permissionService->getEffectiveCompanyId($user);
        $request->attributes->set('effective_company_id', $effectiveCompanyId);
        $request->attributes->set('is_company_owner', $this->permissionService->isCompanyOwner($user));
        $request->attributes->set('is_employee', $this->permissionService->isEmployee($user));

        return $next($request);
    }
}
