<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

    public function boot(): void
    {
        $this->registerPolicies();

        // تحديد مسار مفاتيح OAuth
        Passport::loadKeysFrom(storage_path('oauth'));

        // ملاحظة: إعدادات صلاحية الـ Tokens موجودة في AppServiceProvider
        // وتستخدم القيم من config/passport.php
    }
}
