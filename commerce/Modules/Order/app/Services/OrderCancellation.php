<?php

namespace Modules\Order\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Admin\Models\Setting;
use Modules\Catalog\Models\StockMovement;
use Modules\Catalog\Services\StockLedger;
use Modules\Order\Events\OrderCancelled;
use Modules\Order\Models\Order;

/**
 * Annulation d'une commande — règle unique pour la cliente et pour la boutique.
 *
 * Deux choses en découlent :
 *
 *  · Une fenêtre de rétractation. La cliente annule elle-même pendant un délai
 *    réglable (24 h par défaut) ; passé ce délai la commande est en préparation
 *    et l'annulation se négocie avec un agent. Régler le délai à 0 ferme
 *    l'annulation en libre-service sans rien retirer à la boutique.
 *
 *  · Le retour du stock. Les articles sont décomptés à la création : une
 *    commande annulée dont le stock ne revient pas fait disparaître de la
 *    vente des pots qui sont toujours en rayon. Le retour passe par le
 *    registre, et une seule fois — un aller-retour de statut ne doit pas
 *    créditer le stock deux fois.
 */
class OrderCancellation
{
    /** Réglage du délai d'annulation, en heures, et sa valeur par défaut. */
    public const DELAY_SETTING = 'order_cancellation_delay_hours';
    public const DELAY_DEFAULT = 24;

    /**
     * Statuts encore annulables. Au-delà, le colis est préparé ou parti :
     * ce n'est plus une annulation mais un retour, qui se traite autrement.
     */
    public const CANCELLABLE_STATUSES = ['pending', 'confirmed'];

    public function __construct(private readonly StockLedger $ledger) {}

    // ── Fenêtre de rétractation ────────────────────────────────────────────

    /** Délai d'annulation en heures. 0 = annulation en libre-service fermée. */
    public function delayHours(): int
    {
        $valeur = Setting::get(self::DELAY_SETTING);

        if ($valeur === null || $valeur === '') {
            return self::DELAY_DEFAULT;
        }

        return max(0, (int) $valeur);
    }

    /** Instant jusqu'auquel la cliente peut annuler seule, ou null si fermé. */
    public function deadlineFor(Order $order): ?CarbonInterface
    {
        $heures = $this->delayHours();

        return $heures > 0 ? $order->created_at->copy()->addHours($heures) : null;
    }

    /**
     * La cliente peut-elle annuler cette commande maintenant ?
     * Si non, `refusalReason()` explique pourquoi, dans les mots de la cliente.
     */
    public function customerCanCancel(Order $order): bool
    {
        return $this->refusalReason($order) === null;
    }

    /**
     * Motif de refus, sous forme de code, ou null si l'annulation est ouverte.
     *
     * Un code plutôt qu'une phrase : la boutique est bilingue, et un texte
     * français renvoyé par l'API s'afficherait tel quel dans l'interface
     * anglaise. Le code se traduit des deux côtés, la phrase française reste
     * disponible pour les réponses d'erreur de l'API.
     *
     * L'ordre des contrôles suit ce qui est le plus parlant à lire : ce qui est
     * déjà annulé d'abord, puis l'avancement, puis le paiement, puis le délai.
     */
    public function refusalCode(Order $order): ?string
    {
        if ($order->status === 'cancelled') {
            return 'already_cancelled';
        }

        if (in_array($order->status, ['shipped', 'delivered'], true)) {
            return 'shipped';
        }

        if (! in_array($order->status, self::CANCELLABLE_STATUSES, true)) {
            return 'in_progress';
        }

        // Une commande réglée engage un remboursement : c'est un geste de
        // caisse, pas un clic. On oriente vers un agent plutôt que de laisser
        // la cliente annuler un paiement qu'on devra lui rendre à la main.
        if ($order->paid_at) {
            return 'already_paid';
        }

        $limite = $this->deadlineFor($order);

        if ($limite === null) {
            return 'closed';
        }

        if (now()->greaterThan($limite)) {
            return 'expired';
        }

        return null;
    }

    /** Motif de refus rédigé pour la cliente, ou null si l'annulation est ouverte. */
    public function refusalReason(Order $order): ?string
    {
        return match ($this->refusalCode($order)) {
            'already_cancelled' => 'Cette commande est déjà annulée.',
            'in_progress'       => "Cette commande est déjà en cours de préparation : contactez-nous pour l'annuler.",
            'shipped'           => "Cette commande est déjà partie. Une annulation reste possible en nous contactant, mais les frais de livraison resteront à votre charge.",
            'already_paid'      => 'Cette commande est déjà réglée : contactez-nous pour son annulation et son remboursement.',
            'closed'            => "L'annulation en ligne n'est pas disponible : contactez-nous et nous nous en occupons.",
            'expired'           => "Le délai d'annulation en ligne est dépassé : contactez-nous et nous nous en occupons.",
            default             => null,
        };
    }

    // ── Annulation ─────────────────────────────────────────────────────────

    /**
     * Annule la commande et remet les articles en stock.
     *
     * @param  bool  $checkWindow  false pour une annulation décidée par la
     *                             boutique : elle n'est pas soumise au délai.
     *
     * @throws \RuntimeException  si la cliente n'a plus le droit d'annuler
     */
    public function cancel(Order $order, ?string $reason = null, bool $checkWindow = true): Order
    {
        if ($checkWindow && ($refus = $this->refusalReason($order))) {
            throw new \RuntimeException($refus);
        }

        DB::transaction(function () use ($order, $reason) {
            $this->restoreStock($order, $reason);

            $metadata = $order->metadata ?? [];
            $metadata['cancelled_at'] = now()->toIso8601String();
            if ($reason) {
                $metadata['cancellation_reason'] = $reason;
            }

            $order->update([
                'status'   => 'cancelled',
                'metadata' => $metadata,
            ]);
        });

        $order->refresh()->load(['items', 'user']);

        event(new OrderCancelled($order));

        return $order;
    }

    /**
     * Remet en stock les articles d'une commande annulée.
     *
     * Marquée dans les métadonnées : sans ce garde-fou, repasser une commande
     * de « annulée » à « annulée » — ou l'annuler depuis deux écrans — créditerait
     * le stock plusieurs fois, et le compteur annoncerait des pots qui n'existent
     * pas.
     */
    public function restoreStock(Order $order, ?string $reason = null): void
    {
        $metadata = $order->metadata ?? [];

        if (! empty($metadata['stock_restored_at'])) {
            return;
        }

        $order->loadMissing(['items.product', 'items.variant']);

        foreach ($order->items as $ligne) {
            if (! $ligne->product) {
                continue;   // produit supprimé du catalogue depuis la commande
            }

            $this->ledger->in(
                $ligne->product,
                $ligne->variant,
                $ligne->quantity,
                StockMovement::TYPE_CANCELLATION,
                [
                    'reference' => $order,
                    'reason'    => $reason
                        ? "Annulation {$order->number} — {$reason}"
                        : "Annulation {$order->number}",
                ],
            );
        }

        $metadata['stock_restored_at'] = now()->toIso8601String();
        $order->metadata = $metadata;
        $order->save();
    }
}
