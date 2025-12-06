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

        // لو أردت تحريك أو تحديد مسار المفاتيح
        Passport::loadKeysFrom(storage_path('oauth'));


        // مدة صلاحية Access Token (15 دقيقة)
        Passport::tokensExpireIn(now()->addMinutes(15));
        
        // مدة صلاحية Refresh Token (7 أيام)
        Passport::refreshTokensExpireIn(now()->addDays(7));
    }
}
