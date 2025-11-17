<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles  List of allowed roles (e.g., 'company', 'admin', 'hr', 'manager')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح بالدخول - يجب تسجيل الدخول'
            ], 401);
        }

        // Check if user's user_type is in the allowed roles
        if (!in_array($user->user_type, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لديك صلاحية للوصول لهذا المورد',
                'required_roles' => $roles,
                'your_role' => $user->user_type
            ], 403);
        }

        return $next($request);
    }
}

