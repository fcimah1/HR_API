<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\PermissionService;
use App\Services\SimplePermissionService;

class CheckPermission
{
    public function __construct(
        private readonly SimplePermissionService $permissionService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح بالدخول'
            ], 401);
        }

        $userType = strtolower(trim($user->user_type ?? ''));

        // مستخدم الشركة له صلاحيات كاملة ولا يحتاج للتحقق من الأدوار
        if ($userType === 'company') {
            return $next($request);
        }

        // التحقق من وجود الدور
        if (!$user->staffRole) {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم غير مرتبط بدور',
                'required_permissions' => $permissions
            ], 403);
        }

        // إذا لم يتم تمرير صلاحيات، السماح بالمرور
        if (empty($permissions)) {
            return $next($request);
        }

        // التحقق من الصلاحيات
        $hasPermission = false;
        $logic = 'OR'; // افتراضي OR logic

        // التحقق من وجود AND logic
        if (in_array('AND', $permissions)) {
            $logic = 'AND';
            $permissions = array_filter($permissions, fn($p) => $p !== 'AND');
        }

        if ($logic === 'AND') {
            // يجب أن يملك جميع الصلاحيات
            $hasPermission = true;
            foreach ($permissions as $permission) {
                if (!$this->permissionService->checkPermission($user, $permission)) {
                    $hasPermission = false;
                    break;
                }
            }
        } else {
            // يكفي أن يملك إحدى الصلاحيات
            $hasPermission = false;
            foreach ($permissions as $permission) {
                if ($this->permissionService->checkPermission($user, $permission)) {
                    $hasPermission = true;
                    break;
                }
            }
        }

        if (!$hasPermission) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لديك صلاحية للوصول لهذا المورد',
                'required_permissions' => $permissions,
                'logic' => $logic,
                'your_permissions' => $this->permissionService->getUserPermissions($user),
                'your_role' => $user->getRoleName()
            ], 403);
        }

        return $next($request);
    }
}
