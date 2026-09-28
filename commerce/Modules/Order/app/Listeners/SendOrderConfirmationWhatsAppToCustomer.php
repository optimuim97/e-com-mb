<?php

namespace Modules\Order\Listeners;

use App\Support\NotificationSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Auth\Services\CustomerWhatsAppService;
use Modules\Order\Events\OrderPlaced;
use Modules\Order\Services\InvoiceService;
use Modules\Order\Services\OrderNotificationMessage;

/**
 * Envoie la confirmation de commande au client par WhatsApp,
 * accompagnée de la facture PDF.
 *
 * Canal principal pour la clientèle : la plupart des clients n'ont pas
 * d'email — l'email (SendOrderConfirmationToCustomer) reste le secours.
 *
 * ShouldQueue : ne bloque pas la réponse HTTP (appel API externe + PDF).
 */
class SendOrderConfirmationWhatsAppToCustomer implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function __construct(
        private readonly CustomerWhatsAppService $whatsapp,
        private readonly InvoiceService $invoiceService,
        private readonly OrderNotificationMessage $redacteur,
    ) {}

    public function handle(OrderPlaced $event): void
    {
        // Clé distincte de `whatsapp_notify_customer`, qui malgré son nom
        // commande l'affichage d'un lien sur la page de confirmation et non
        // l'envoi d'un message. Les confondre mettrait deux comportements sans
        // rapport sous le même interrupteur.
        //
        // La coupure ne concerne que les messages déclenchés par la boutique :
        // les codes à usage unique empruntent un autre chemin et ne doivent
        // jamais être coupés ici, sans quoi la cliente ne peut plus se connecter.
        if (! NotificationSettings::enabled('whatsapp_order_notify_customer')) {
            return;
        }

        $order = $event->order->loadMissing(['user', 'items.product']);
        $addr  = $order->shipping_address ?? [];

        // Priorité : téléphone de l'adresse de livraison, sinon téléphone du compte
        $phone = $addr['phone'] ?? $order->user?->phone ?? null;
        if (! $phone) {
            Log::info("OrderConfirmation WhatsApp : pas de téléphone pour la commande {$order->number}, envoi sauté.");
            return;
        }

        // 1) Message de confirmation.
        //
        // Une cliente qui commande sur le site n'a le plus souvent jamais écrit
        // au WhatsApp de la boutique : la fenêtre de 24 h est fermée et seul un
        // modèle approuvé passe. Le récapitulatif complet part quand même en
        // texte libre pour celles qui viennent de discuter avec la boutique.
        $this->whatsapp->sendSessionOrTemplate(
            $phone,
            $this->redacteur->build($order),
            'order_confirmation',
            [
                $this->prenom($order),
                $order->number,
                number_format((float) $order->total, 0, ',', ' ') . ' F CFA',
            ],
        );

        // 2) Facture PDF jointe (échec non bloquant : le message texte est déjà parti)
        try {
            $pdf = $this->invoiceService->generate($order);
            $this->whatsapp->sendDocument(
                $phone,
                $pdf->output(),
                $this->invoiceService->filename($order),
                "Facture de votre commande {$order->number}",
            );
        } catch (\Throwable $e) {
            Log::warning("OrderConfirmation WhatsApp : facture non envoyée pour {$order->number} : " . $e->getMessage());
        }
    }

    /**
     * Prénom de la cliente pour l'ouverture du modèle.
     *
     * L'adresse de livraison prime sur le compte : c'est le nom que la cliente
     * vient d'écrire elle-même, donc celui auquel elle répond.
     */
    private function prenom(\Modules\Order\Models\Order $order): string
    {
        $addr = $order->shipping_address ?? [];

        return trim((string) ($addr['first_name'] ?? ''))
            ?: (explode(' ', (string) ($order->user?->name ?? ''))[0] ?: 'cliente');
    }

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(30);
    }

    public function failed(OrderPlaced $event, \Throwable $e): void
    {
        Log::error("Échec confirmation WhatsApp pour {$event->order->number} : " . $e->getMessage());
    }
}
