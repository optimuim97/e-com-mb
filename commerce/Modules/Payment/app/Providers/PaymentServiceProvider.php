<?php

namespace Modules\Payment\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Modules\Payment\Console\TestGeniusPayCommand;

class PaymentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__ . '/../../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                TestGeniusPayCommand::class,
            ]);
        }
    }
}
