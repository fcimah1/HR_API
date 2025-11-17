<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
        ->withProviders([
            Laravel\Passport\PassportServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'simple.permission' => \App\Http\Middleware\SimplePermissionCheck::class,
            'simple.company' => \App\Http\Middleware\SimpleCompanyIsolation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // إرجاع JSON عند عدم التوثيق بدلاً من محاولة التحويل إلى Route login
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح - يجب تسجيل الدخول',
            ], 401);
        });
    })->create();
