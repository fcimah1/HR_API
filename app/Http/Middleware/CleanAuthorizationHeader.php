<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CleanAuthorizationHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Clean up malformed Authorization header
        if ($request->hasHeader('Authorization')) {
            $authHeader = $request->header('Authorization');
            
            // Log the original header for debugging
            \Log::info('Original Authorization Header: ' . $authHeader);
            
            // Fix various malformed patterns
            if (preg_match('/^bearer\s+bearer\s+(.+)$/i', $authHeader, $matches)) {
                // Double bearer: "bearer bearer token"
                $cleanToken = trim($matches[1]);
                $request->headers->set('Authorization', 'Bearer ' . $cleanToken);
                \Log::info('Fixed double bearer, new header: Bearer ' . $cleanToken);
            }
            elseif (preg_match('/^bearer\s+(.+)$/i', $authHeader, $matches)) {
                // Lowercase bearer: "bearer token"
                $cleanToken = trim($matches[1]);
                $request->headers->set('Authorization', 'Bearer ' . $cleanToken);
                \Log::info('Fixed lowercase bearer, new header: Bearer ' . $cleanToken);
            }
            elseif (preg_match('/^(.+)$/i', $authHeader, $matches) && !preg_match('/^Bearer\s+/i', $authHeader)) {
                // Token without Bearer prefix
                $cleanToken = trim($matches[1]);
                if (strlen($cleanToken) > 10 && !str_contains(strtolower($cleanToken), 'bearer')) {
                    $request->headers->set('Authorization', 'Bearer ' . $cleanToken);
                    \Log::info('Added Bearer prefix, new header: Bearer ' . $cleanToken);
                }
            }
        }
        
        return $next($request);
    }
}
