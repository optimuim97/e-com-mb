<?php

namespace Modules\Customer\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Modules\Customer\Console\ImportCustomersCommand;

class CustomerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__ . '/../../routes/api.php');

        // Reprise du carnet clientes existant de la boutique.
        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportCustomersCommand::class,
            ]);
        }
    }
}
