<?php

namespace Modules\Order\Support;

use Modules\Order\Models\Order;

/**
 * « Cette commande peut-elle partir ? »
 *
 * Deux refus, et seulement ceux-là : des frais de livraison encore à fixer
 * (on livrerait sans avoir facturé le transport) et une commande impayée qui
 * n'est pas en paiement à la livraison (on livrerait sans encaisser).
 *
 * Extrait du contrôleur des commandes le jour où la préparation d'une tournée
 * a eu besoin de la même règle. Une expédition unitaire et une expédition en
 * masse qui divergeraient, c'est la seconde qui laisserait passer ce que la
 * première refuse — et elle traite vingt commandes à la fois.
 */
final class ShippingBlockers
{
    /**
     * Raisons de ne pas expédier. Tableau vide = la commande peut partir.
     *
     * @return array<int, string>
     */
    public static function for(Order $order): array
    {
        $blockers = [];

        // Couvre le hors zone ET toute destination à frais négociés dont le
        // montant serait resté à zéro.
        if (ShippingFeeRequirement::isPending($order)) {
            $blockers[] = ShippingFeeRequirement::message($order);
        }

        $isCashOnDelivery = in_array($order->payment_method, ['cod', 'delivery'], true);
        if (! $order->paid_at && ! $isCashOnDelivery) {
            $blockers[] = "La commande n'est pas payée.";
        }

        return $blockers;
    }

    public static function none(Order $order): bool
    {
        return self::for($order) === [];
    }
}
