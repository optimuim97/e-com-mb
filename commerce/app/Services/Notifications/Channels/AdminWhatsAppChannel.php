<?php

namespace App\Services\Notifications\Channels;

use App\Services\Notifications\Contracts\NotificationChannelInterface;
use App\Support\NotificationSettings;
use Illuminate\Support\Facades\Log;
use Modules\Admin\Models\Setting;
use Modules\Auth\Services\CustomerWhatsAppService;
use App\Support\Shop;

/**
 * Alerte la gérante sur son WhatsApp à chaque mouvement de commande.
 *
 * L'envoi passe par CustomerWhatsAppService — malgré son nom, c'est le seul
 * endroit qui sait arbitrer entre texte libre et modèle approuvé selon la
 * fenêtre de 24 h. Dupliquer l'appel HTTP ici, c'était garantir qu'un des deux
 * chemins finisse par rater cette règle.
 */
class AdminWhatsAppChannel implements NotificationChannelInterface
{
    public function notify(string $event, array $payload): void
    {
        // Interrupteur distinct de celui des clientes : couper les alertes de
        // la gérante pendant un essai ne doit pas priver la clientèle de sa
        // confirmation de commande.
        if (! NotificationSettings::enabled('whatsapp_order_notify_admin')) {
            return;
        }

        $adminPhone = Setting::get('whatsapp_admin_number', '');
        $apiToken   = config('services.whatsapp.token', '');
        $phoneId    = config('services.whatsapp.phone_number_id', '');

        if (! $adminPhone || ! $apiToken || ! $phoneId) {
            Log::info("[WhatsAppChannel] Non configuré — notification ignorée pour {$event}");
            return;
        }

        $message = $this->buildMessage($event, $payload);
        $phone   = preg_replace('/\D/', '', $adminPhone);

        // La gérante écrit rarement au numéro de sa propre boutique : sa
        // fenêtre de 24 h est fermée le plus clair du temps, donc modèle.
        $envoye = app(CustomerWhatsAppService::class)->sendSessionOrTemplate(
            $phone,
            $message,
            'admin_new_order',
            [
                (string) ($payload['number'] ?? '—'),
                (string) ($payload['customer_name'] ?? '—'),
                number_format((float) ($payload['total'] ?? 0), 0, ',', ' ') . ' F CFA',
            ],
        );

        if ($envoye) {
            Log::info("[WhatsAppChannel] Message WA envoyé pour {$event}", ['to' => $phone]);
        } else {
            Log::warning("[WhatsAppChannel] Échec envoi WA pour {$event}", ['to' => $phone]);
        }
    }

    public function shouldNotify(string $event): bool
    {
        return in_array($event, ['order.placed', 'order.shipped', 'order.cancelled']);
    }

    // ── Construction du message ───────────────────────────────────────────────

    private function buildMessage(string $event, array $payload): string
    {
        return match ($event) {
            'order.placed'    => $this->orderPlacedMessage($payload),
            'order.shipped'   => $this->orderShippedMessage($payload),
            'order.cancelled' => $this->orderCancelledMessage($payload),
            default           => "🔔 Événement : {$event}\nN° {$payload['number']}",
        };
    }

    private function orderPlacedMessage(array $p): string
    {
        $method = match ($p['payment_method'] ?? '') {
            'wave'         => '💙 Wave',
            'orange_money' => '🟠 Orange Money',
            'cod'          => '🚚 À la livraison',
            'delivery'     => '🚚 À la livraison',
            default        => ucfirst($p['payment_method'] ?? '—'),
        };

        $items = collect($p['items'] ?? [])->map(function ($i) {
            $label = $i['name'];
            if ($i['variant'] ?? null) $label .= " ({$i['variant']})";
            return "  • {$label} ×{$i['qty']}";
        })->implode("\n");

        $total  = number_format((float)($p['total'] ?? 0), 0, ',', ' ');
        $phone  = $p['customer_phone'] ?? '—';
        $commune = $p['commune'] ?? '—';

        $shop = Shop::name();

        return <<<MSG
🛍️ *Nouvelle commande {$shop}*

N° : *{$p['number']}*
👤 Client : {$p['customer_name']} — {$phone}
📍 Commune : {$commune}
💳 Paiement : {$method}
📦 Articles :
{$items}

💰 Total : *{$total} F CFA*

Répondre au client : {$phone}
MSG;
    }

    private function orderShippedMessage(array $p): string
    {
        $tracking = $p['tracking_number'] ?? null;
        $line = $tracking ? "\n📦 Suivi : *{$tracking}*" : '';
        return "🚚 *Commande expédiée*\n\nN° : {$p['number']}\n👤 {$p['customer_name']}{$line}";
    }

    private function orderCancelledMessage(array $p): string
    {
        $total = number_format((float)($p['total'] ?? 0), 0, ',', ' ');
        return "❌ *Commande annulée*\n\nN° : {$p['number']}\n👤 {$p['customer_name']}\n💰 {$total} F CFA";
    }
}
