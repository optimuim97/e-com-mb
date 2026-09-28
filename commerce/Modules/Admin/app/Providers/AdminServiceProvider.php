<?php

namespace Modules\Admin\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Modules\Admin\Console\WhatsAppDoctorCommand;

class AdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__ . '/../../routes/api.php');

        // Diagnostic de la chaîne WhatsApp Cloud API.
        if ($this->app->runningInConsole()) {
            $this->commands([
                WhatsAppDoctorCommand::class,
            ]);
        }
    }
}
