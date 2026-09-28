<?php

namespace App\Services\Notifications;

use App\Services\Notifications\Contracts\NotificationChannelInterface;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;

/**
 * Service centralisé d'administration des notifications.
 *
 * Chaque canal (email, Reverb, WhatsApp…) est injecté via le conteneur
 * Laravel. Pour ajouter un nouveau canal : implémenter NotificationChannelInterface
 * et l'enregistrer dans NotificationServiceProvider.
 *
 * Usage :
 *   app(AdminNotificationService::class)->orderPlaced($order);
 */
class AdminNotificationService
{
    /**
     * @param NotificationChannelInterface[] $channels
     */
    public function __construct(private readonly array $channels) {}

    // ── API publique ──────────────────────────────────────────────────────────

    /** Notifie l'admin qu'une nouvelle commande a été passée. */
    public function orderPlaced(Order $order): void
    {
        $this->dispatch('order.placed', $this->buildOrderPayload($order));
    }

    /** Notifie l'admin qu'une commande a été expédiée. */
    public function orderShipped(Order $order): void
    {
        $this->dispatch('order.shipped', $this->buildOrderPayload($order));
    }

    /** Notifie l'admin qu'une commande a été annulée. */
    public function orderCancelled(Order $order): void
    {
        $this->dispatch('order.cancelled', $this->buildOrderPayload($order));
    }

    /**
     * Point d'entrée générique.
     * Permet d'envoyer n'importe quel événement sur tous les canaux.
     */
    public function dispatch(string $event, array $payload): void
    {
        foreach ($this->channels as $channel) {
            if (! $channel->shouldNotify($event)) {
                continue;
            }

            try {
                $channel->notify($event, $payload);
            } catch (\Throwable $e) {
                Log::error('[AdminNotificationService] Échec canal ' . get_class($channel), [
                    'event'   => $event,
                    'error'   => $e->getMessage(),
                    'channel' => class_basename($channel),
                ]);
            }
        }
    }

    // ── Normalisation payload ─────────────────────────────────────────────────

    private function buildOrderPayload(Order $order): array
    {
        $order->loadMissing(['items', 'user']);

        return [
            'id'             => $order->id,
            'number'         => $order->number,
            'status'         => $order->status,
            'customer_name'  => $order->user?->name  ?? '—',
            'customer_phone' => $order->user?->phone  ?? '—',
            'customer_email' => $order->user?->email  ?? null,
            'payment_method' => $order->payment_method,
            'tracking_number' => $order->tracking_number,
            'total'          => (float) $order->total,
            'currency'       => $order->currency,
            'commune'        => $order->shipping_address['address_line1']
                                ?? $order->shipping_address['city']
                                ?? '—',
            'items_count'    => $order->items->count(),
            'items'          => $order->items->map(fn ($i) => [
                'name'     => $i->product_name,
                'qty'      => $i->quantity,
                'price'    => (float) $i->unit_price,
                // Total de ligne tel qu'il est stocké, et non quantité × prix
                // unitaire : une promotion peut porter une remise propre à
                // l'article, que le recalcul effacerait sans le dire.
                'total'    => (float) $i->total_price,
                'variant'  => $i->variant_label,
            ])->toArray(),
            'created_at'     => $order->created_at?->toISOString(),
            'url'            => url('/admin/orders/' . $order->id),
        ];
    }
}
