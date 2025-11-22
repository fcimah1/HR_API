<?php

namespace App\Logging;

use App\Models\SystemLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

class DatabaseLoggerHandler extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        try {
            $userId = null;
            $userName = null;

            try {
                $user = Auth::user();
                if ($user) {
                    $userId = $user->user_id;
                    $userName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                }
            } catch (\Throwable $e) {
                // نتجاهل أخطاء المصادقة (مثل Token فاسد) لنتمكن من تسجيل الخطأ نفسه
            }

            SystemLog::create([
                'level' => $record->level->name,
                'message' => $record->message,
                'context' => $record->context,
                'user_id' => $userId,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'url' => Request::fullUrl(),
                'method' => Request::method(),
                'request' => Request::all(),
                'response' => $record->extra['response'] ?? null,
                'user_name' => $userName,
            ]);
        } catch (\Throwable $e) {
            // في حالة فشل الكتابة في قاعدة البيانات، نسجل الخطأ في الملف العادي لنعرف السبب
            // نستخدم file_put_contents مباشرة لتجنب الدخول في حلقة لا نهائية إذا استخدمنا Log::error
            $logPath = storage_path('logs/laravel.log');
            $errorMessage = '[' . date('Y-m-d H:i:s') . '] DatabaseLoggerHandler Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
            file_put_contents($logPath, $errorMessage, FILE_APPEND);
        }
    }
}
