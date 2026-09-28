<?php

namespace Modules\Order\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Order\Events\OrderPlaced;
use Modules\Order\Mail\OrderConfirmationMail;
use Modules\Order\Support\CustomerEmail;

/**
 * Envoie l'e-mail de confirmation au client (avec la facture PDF en pièce jointe).
 *
 * ShouldQueue : ne bloque pas la réponse HTTP (génération PDF + SMTP).
 * En dev : QUEUE_CONNECTION=sync dans .env pour exécuter immédiatement.
 */
class SendOrderConfirmationToCustomer implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->loadMissing(['user', 'items.product', 'payments']);

        // L'adresse joignable, et la règle qui la choisit, vivent dans
        // CustomerEmail : la relance de règlement applique la même.
        $email = CustomerEmail::for($order);

        if (! $email) {
            Log::info("OrderConfirmationMail: pas d'email réel pour la commande {$order->number}, envoi sauté.");

            return;
        }

        Mail::to($email)->send(new OrderConfirmationMail($order));
    }

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(30);
    }

    public function failed(OrderPlaced $event, \Throwable $e): void
    {
        Log::error("Échec OrderConfirmationMail pour {$event->order->number} : " . $e->getMessage());
    }
}
