<?php

namespace Modules\Cart\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Modules\Cart\Console\Commands\SendAbandonmentReminders;

class CartServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__ . '/../../routes/api.php');

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Register Blade views under cart:: namespace
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'cart');

        // Register Artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                SendAbandonmentReminders::class,
            ]);
        }
    }
}
