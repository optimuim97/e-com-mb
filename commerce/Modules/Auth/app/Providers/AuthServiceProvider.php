<?php

namespace Modules\Auth\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__ . '/../../routes/api.php');

        // Routes web (OAuth social) : middleware "web" pour la session (state CSRF),
        // sans préfixe /api → /auth/{provider}/redirect, /auth/{provider}/callback
        Route::middleware('web')->group(__DIR__ . '/../../routes/web.php');
    }
}
