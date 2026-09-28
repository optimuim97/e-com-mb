<?php

namespace Modules\Catalog\Listeners;

use App\Services\Notifications\AdminNotificationService;
use App\Support\NotificationSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Catalog\Events\StockAlertRaised;

/**
 * Prévient l'administration qu'un article est en rupture ou près de l'être.
 *
 * Un réassort se décide sur plusieurs jours : découvrir la rupture le jour où
 * une cliente la signale, c'est déjà trop tard.
 */
class NotifyAdminsOnStockAlert implements ShouldQueue
{
    public function __construct(private readonly AdminNotificationService $notificationService) {}

    public function handle(StockAlertRaised $event): void
    {
        // Interrupteur propre aux alertes de stock : les couper pendant une
        // reprise d'inventaire ne doit pas éteindre les alertes de commande.
        if (! NotificationSettings::enabled('stock_alert_notify_admin')) {
            return;
        }

        $produit     = $event->product;
        $declinaison = $event->variant;

        $this->notificationService->dispatch(
            $event->isOutOfStock() ? 'stock.out' : 'stock.low',
            [
                'product_id'   => $produit->id,
                'product_name' => $produit->name,
                'variant_id'   => $declinaison?->id,
                'variant_label' => $declinaison?->label,
                'sku'          => $declinaison?->sku ?? $produit->sku,
                'stock'        => $event->movement->stock_after,
                'threshold'    => (int) $produit->low_stock_threshold,
                'level'        => $event->level,
                // Le front admin range ses notifications par « number » ; sans
                // clé lisible, l'alerte s'afficherait sans intitulé.
                'number'       => $declinaison?->sku ?? $produit->sku ?? $produit->name,
            ],
        );
    }
}
