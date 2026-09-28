<?php

namespace Modules\Order\Support;

use Modules\Order\Models\Order;

/**
 * Libellé d'un statut de commande, tel qu'il s'affiche en administration.
 *
 * `pending` recouvrait deux situations très différentes : « la cliente n'a pas
 * encore payé » et « nous n'avons pas encore validé ». Sur une expédition, ce
 * n'est pas un détail — c'est la différence entre relancer la cliente et
 * préparer le colis. L'agent devait ouvrir la fiche pour trancher.
 *
 * Distinction limitée aux expéditions : à Abidjan on encaisse à la livraison,
 * une commande non payée y est parfaitement normale et n'attend rien.
 */
final class OrderStatusLabel
{
    private const LABELS = [
        'pending'    => 'En attente',
        'confirmed'  => 'Confirmée',
        'processing' => 'En préparation',
        'shipped'    => 'Expédiée',
        'delivered'  => 'Livrée',
        'cancelled'  => 'Annulée',
        'refunded'   => 'Remboursée',
    ];

    public static function for(Order $order): string
    {
        if (self::isAwaitingPayment($order)) {
            return 'En attente de paiement';
        }

        return self::LABELS[$order->status] ?? $order->status;
    }

    /**
     * Expédition enregistrée mais pas encore réglée.
     *
     * Le règlement vaut confirmation sur ces destinations : dès qu'il est
     * encaissé — par une passerelle ou saisi par un agent — la commande passe
     * à « Confirmée ». Cet état ne dure donc que le temps de l'attente.
     */
    public static function isAwaitingPayment(Order $order): bool
    {
        return $order->status === 'pending'
            && ! $order->paid_at
            && ShippingFeeRequirement::applies($order);
    }
}
