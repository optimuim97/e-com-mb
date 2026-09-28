<?php

namespace Modules\Order\Listeners;

use App\Services\Notifications\AdminNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Order\Events\OrderPlaced;

/**
 * Listener découplé : délègue entièrement au service de notification.
 * Implémente ShouldQueue pour ne pas bloquer la réponse HTTP.
 *
 * En développement, configurer QUEUE_CONNECTION=sync dans .env
 * pour une exécution synchrone sans worker.
 */
class NotifyAdminsOnOrderPlaced implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function __construct(
        private readonly AdminNotificationService $notificationService,
    ) {}

    public function handle(OrderPlaced $event): void
    {
        $this->notificationService->orderPlaced($event->order);
    }

    /** Ré-essai automatique en cas d'échec (ex. SMTP down) */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(30);
    }
}
