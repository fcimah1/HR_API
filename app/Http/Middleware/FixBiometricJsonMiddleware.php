<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware لإصلاح JSON غير الصالح من أجهزة البصمة
 * يحول { {...}, {...} } إلى [ {...}, {...} ]
 */
class FixBiometricJsonMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // فقط لـ endpoints البصمة
        if (!str_contains($request->path(), 'biometric/logs')) {
            return $next($request);
        }

        $content = $request->getContent();

        // DEBUG: Log raw content
        \Illuminate\Support\Facades\Log::info('Biometric Middleware - Raw content received', [
            'content_length' => strlen($content),
            'content_preview' => substr($content, 0, 500), // أول 500 حرف
            'path' => $request->path(),
        ]);

        // إذا كان فارغاً، نتجاوز
        if (empty($content)) {
            return $next($request);
        }

        // محاولة parse الـ JSON أولاً
        $decoded = json_decode($content, true);

        // إذا نجح الـ parse، لا نحتاج إصلاح
        if (json_last_error() === JSON_ERROR_NONE) {
            // لكن نتحقق إذا كان array أم object
            if (is_array($decoded) && !$this->isAssociativeArray($decoded)) {
                // Array عادي - نمرره كـ logs
                $request->merge(['logs' => $decoded]);
            }
            return $next($request);
        }

        // محاولة إصلاح الـ JSON الخاطئ
        // تحويل { {...}, {...} } إلى [ {...}, {...} ]
        $fixedContent = $this->fixMalformedJson($content);

        if ($fixedContent) {
            $decoded = json_decode($fixedContent, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $request->merge(['logs' => $decoded]);
            }
        }

        return $next($request);
    }

    /**
     * إصلاح JSON الخاطئ
     */
    private function fixMalformedJson(string $content): ?string
    {
        $content = trim($content);

        // إذا بدأ بـ { وانتهى بـ } ويحتوي على objects متعددة
        if (str_starts_with($content, '{') && str_ends_with($content, '}')) {
            // نزيل الـ { } الخارجية ونضيف [ ]
            $inner = substr($content, 1, -1);
            $inner = trim($inner);

            // نتحقق إذا كان يبدأ بـ { (object داخلي)
            if (str_starts_with($inner, '{')) {
                $fixed = '[' . $inner . ']';

                // نتحقق من صحة الـ JSON بعد الإصلاح
                json_decode($fixed);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $fixed;
                }
            }
        }

        return null;
    }

    /**
     * التحقق من أن الـ array associative أم indexed
     */
    private function isAssociativeArray(array $arr): bool
    {
        if (empty($arr)) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
