<?php

namespace App\Providers;

use App\Services\Notifications\AdminNotificationService;
use App\Services\Notifications\Channels\AdminEmailChannel;
use App\Services\Notifications\Channels\AdminReverbChannel;
use App\Services\Notifications\Channels\AdminWhatsAppChannel;
use Illuminate\Support\ServiceProvider;

/**
 * Fournisseur du service de notifications admin.
 *
 * Pour ajouter un canal : créer une classe implémentant
 * NotificationChannelInterface et l'ajouter au tableau $channels ci-dessous.
 */
class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdminNotificationService::class, function () {
            return new AdminNotificationService(channels: [
                new AdminEmailChannel(),
                new AdminReverbChannel(),
                new AdminWhatsAppChannel(),
            ]);
        });
    }

    public function boot(): void
    {
        // Le binding est automatique via le singleton enregistré ci-dessus.
        // Les listeners sont enregistrés dans chaque module (ex. OrderServiceProvider).
    }
}
