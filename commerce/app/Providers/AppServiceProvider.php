<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        /*
         * Enregistrer la route /broadcasting/auth avec auth:sanctum (Bearer token).
         * On a retiré channels: de withRouting() dans bootstrap/app.php parce que
         * withRouting(channels:) appelle Broadcast::routes() sans middleware (web/session)
         * dans un callback booted() qui s'exécute APRÈS boot() — ce qui écrasait
         * notre route et causait la 403 sur Echo.private().
         *
         * On charge le fichier channels.php ici pour garder les callbacks de canaux.
         */
        Broadcast::routes(['middleware' => ['auth:sanctum']]);
        require base_path('routes/channels.php');
    }
}
