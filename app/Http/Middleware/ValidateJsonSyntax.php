<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateJsonSyntax
{
    /**
     * التحقق من صحة JSON في الطلب
     * Validate JSON syntax in request body
     */
    public function handle(Request $request, Closure $next): Response
    {
        // فقط للطلبات التي تحتوي على JSON
        if ($request->isJson() || $request->header('Content-Type') === 'application/json') {
            $content = $request->getContent();

            if (!empty($content)) {
                json_decode($content);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    return response()->json([
                        'success' => false,
                        'message' => 'صيغة JSON غير صالحة',
                        'error' => $this->getJsonErrorMessage(json_last_error()),
                        'hint' => 'تأكد من عدم وجود فاصلة زائدة في نهاية الـ JSON',
                    ], 400);
                }
            }
        }

        return $next($request);
    }

    /**
     * ترجمة رسائل خطأ JSON
     */
    private function getJsonErrorMessage(int $error): string
    {
        return match ($error) {
            JSON_ERROR_DEPTH => 'تجاوز الحد الأقصى لعمق JSON',
            JSON_ERROR_STATE_MISMATCH => 'حالة غير متطابقة في JSON',
            JSON_ERROR_CTRL_CHAR => 'رمز تحكم غير صالح في JSON',
            JSON_ERROR_SYNTAX => 'خطأ في صيغة JSON - تأكد من عدم وجود فاصلة زائدة',
            JSON_ERROR_UTF8 => 'ترميز UTF-8 غير صالح',
            default => 'خطأ غير معروف في JSON',
        };
    }
}
