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
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'غير مصرح',
            ], 401);
        }
        
        // إذا لم يتم تحديد أدوار، اسمح بالمرور
        if (empty($roles)) {
            return $next($request);
        }
        
        // التحقق من الأدوار - تشمل أيضاً user_type للـ company و admin
        foreach ($roles as $role) {
            // للـ company و admin و hr - يمكنهم الوصول بناءً على user_type
            if ($user->user_type === $role) {
                return $next($request);
            }
            
            // للـ staff - يتم التحقق من staffRole
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }
        
        return response()->json([
            'status' => 'error',
            'message' => 'ليس لديك الصلاحيات الكافية للوصول لهذا المورد'
        ], 403);
    }
}
