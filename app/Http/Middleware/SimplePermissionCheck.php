<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\SimplePermissionService;

class SimplePermissionCheck
{
    private SimplePermissionService $permissionService;

    public function __construct(SimplePermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    public function handle(Request $request, Closure $next, ?string $permission = null)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'غير مصرح - يجب تسجيل الدخول'], 401);
        }
        // إذا لم يتم تحديد صلاحية، السماح بالمرور
        if (!$permission) {
            return $next($request);
        }

        // التحقق من الصلاحية
        if (!$this->permissionService->checkPermission($user, $permission)) {
            $userPermissions = $this->permissionService->getUserPermissions($user);
            return response()->json([
                'error' => 'غير مصرح - ليس لديك صلاحية للوصول لهذا المورد',
                'required_permission' => $permission,
                'user_id' => $user->user_id,
                'user_name' => $user->first_name . ' ' . $user->last_name,
                'user_permissions_count' => count($userPermissions),
                'has_permission' => in_array($permission, $userPermissions)
            ], 403);
        }

        return $next($request);
    }
}
