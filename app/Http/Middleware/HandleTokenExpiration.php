<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use League\OAuth2\Server\Exception\OAuthServerException;

class HandleTokenExpiration
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (OAuthServerException $e) {
            // Handle token expiration or invalid token
            if ($e->getCode() === 9) { // Access denied code
                return response()->json([
                    'success' => false,
                    'message' => 'Token expired or invalid. Please login again.',
                    'error_code' => 'TOKEN_EXPIRED'
                ], 401);
            }
            
            // Re-throw other OAuth exceptions
            throw $e;
        }
    }
}
