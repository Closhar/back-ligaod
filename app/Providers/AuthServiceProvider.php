<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Настройка Sanctum
        Sanctum::authenticateAccessTokensUsing(function ($accessToken, $isValid) {
            return $isValid && $accessToken->tokenable;
        });

        // Дополнительные Gates для проверки прав
        Gate::define('admin', function ($user) {
            return $user->is_admin;
        });
    }
}
