<?php

namespace Modules\Order\Support;

use Modules\Order\Models\Order;

/**
 * « Les frais de livraison de cette commande sont-ils encore à fixer ? »
 *
 * Règle unique, partagée par les garde-fous d'expédition, la notification de
 * la cliente, la génération d'un lien de paiement et l'écran de traitement.
 *
 * Pourquoi elle existe : à Abidjan, les frais sont connus dès la commande —
 * tarif de la zone, ou tarif de base si la commune n'est pas encore tarifée.
 * Hors Abidjan et à l'international, non : ils dépendent du transporteur, du
 * poids, parfois de la douane. Le total affiché à la création n'est donc pas
 * ferme, et l'annoncer à la cliente avant de l'avoir arrêté, c'est devoir se
 * dédire ensuite — ou livrer à perte.
 */
final class ShippingFeeRequirement
{
    /** Destinations dont les frais ne sont pas connus à la commande. */
    public const NEGOTIATED_DESTINATIONS = [
        PaymentAvailability::INTERIOR,
        PaymentAvailability::INTERNATIONAL,
    ];

    /** Les frais restent-ils à renseigner sur cette commande ? */
    public static function isPending(Order $order): bool
    {
        if (! self::applies($order)) {
            return false;
        }

        // Marqueur posé à la création quand aucune zone n'a pu être tarifée.
        if ((bool) ($order->metadata['shipping_unknown'] ?? false)) {
            return true;
        }

        // Zéro n'est pas un tarif : c'est l'absence de tarif. Aucune livraison
        // n'est gratuite dans cette boutique — voir DeliveryZone::format().
        return (float) $order->shipping_cost <= 0;
    }

    /**
     * La commande relève-t-elle d'une destination à frais négociés ?
     *
     * `destination` est figée à la création et maintenue par Order::booted().
     * Une commande antérieure à cette colonne peut l'avoir vide : on retombe
     * alors sur l'adresse, plutôt que de la traiter comme abidjanaise et de
     * laisser passer une expédition à frais nuls.
     */
    public static function applies(Order $order): bool
    {
        $destination = $order->destination
            ?: PaymentAvailability::destination($order->shipping_address);

        return in_array($destination, self::NEGOTIATED_DESTINATIONS, true);
    }

    /** Message adressé à l'agent qui tente d'agir trop tôt. */
    public static function message(Order $order): string
    {
        $destination = $order->destination
            ?: PaymentAvailability::destination($order->shipping_address);

        return $destination === PaymentAvailability::INTERNATIONAL
            ? "Commande internationale : renseignez les frais de livraison avant d'annoncer un montant à la cliente."
            : 'Livraison ' . lcfirst(HomeZone::outsideLabel()) . " : renseignez les frais de livraison avant d'annoncer un montant à la cliente.";
    }
}
