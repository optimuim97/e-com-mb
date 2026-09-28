<?php

namespace Modules\Order\Support;

use Modules\Delivery\Models\DeliveryZone;

/**
 * Moyens de paiement acceptés selon la destination de livraison.
 *
 * Règle métier (source unique de vérité — le checkout, la commande rapide et
 * le front n'en sont que des reflets) :
 *
 *  - Grand Abidjan      → paiement à la livraison uniquement. Nos livreurs
 *    encaissent sur place ; inutile de faire prépayer une cliente qu'on voit.
 *    Une commune du district encore absente des zones reste tarifée (tarif de
 *    base, réglable en admin) : elle n'est donc jamais « hors zone ».
 *  - Intérieur de la CI → Wave ou Orange Money. On n'expédie à l'intérieur que
 *    ce qui est déjà payé (voir AbidjanZone) : le prépaiement mobile est le
 *    seul moyen d'y arriver.
 *  - International      → aucun paiement en ligne. Frais, délais et douane se
 *    négocient au cas par cas : la commande se finalise avec un agent sur
 *    WhatsApp.
 */
class PaymentAvailability
{
    public const ABIDJAN       = 'abidjan';
    public const INTERIOR      = 'interior';
    public const INTERNATIONAL = 'international';

    /**
     * Destination déduite de l'adresse de livraison.
     *
     * @param  array|null  $address  shipping_address (country, city, commune…)
     */
    public static function destination(?array $address): string
    {
        $country = strtoupper(trim((string) ($address['country'] ?? '')));

        // Pays absent : les formulaires boutique sont pré-remplis en CI, on ne
        // bascule en international que sur un pays explicitement autre.
        if ($country !== '' && $country !== 'CI') {
            return self::INTERNATIONAL;
        }

        return AbidjanZone::isAbidjan($address) ? self::ABIDJAN : self::INTERIOR;
    }

    /**
     * Zone de livraison non tarifée pour cette adresse.
     *
     * Tant que la zone est inconnue, le montant à régler l'est aussi : on ne
     * peut pas demander à la cliente de choisir comment payer une somme qu'on
     * ne sait pas encore calculer. Nos agents fixent les frais puis le mode de
     * règlement, et la cliente est notifiée à ce moment-là.
     */
    public static function isOutOfZone(?array $address): bool
    {
        $city    = trim((string) ($address['city']    ?? ''));
        $commune = trim((string) ($address['commune'] ?? ''));
        $country = strtoupper(trim((string) ($address['country'] ?? ''))) ?: 'CI';

        if ($city === '' && $commune === '') {
            return true;
        }

        // Adresse incomplète ≠ ville non desservie. Abidjan est tarifée à la
        // commune : sans commune, `quote()` renvoie null pour forcer la
        // sélection, pas pour signaler une destination inconnue. Confondre les
        // deux envoyait plus d'une commande sur deux en traitement manuel.
        // La commune est exigée séparément — voir StoreOrderRequest.
        if (self::needsCommune($address)) {
            return false;
        }

        // Repli Abidjan compris : une adresse que ShippingResolver sait facturer
        // ne doit pas être annoncée « hors zone » à la cliente — elle se verrait
        // refuser tout moyen de paiement pour une commande pourtant chiffrée.
        return DeliveryZone::quoteWithAbidjanBase($city, $country, null, $commune ?: null) === null;
    }

    /**
     * Ville tarifée à la commune, dont la commune n'est pas renseignée.
     *
     * La cliente doit compléter son adresse : la zone existe, on ne sait juste
     * pas encore laquelle.
     */
    public static function needsCommune(?array $address): bool
    {
        $city    = trim((string) ($address['city']    ?? ''));
        $commune = trim((string) ($address['commune'] ?? ''));
        $country = strtoupper(trim((string) ($address['country'] ?? ''))) ?: 'CI';

        return $country === 'CI'
            && $commune === ''
            && DeliveryZone::isMultiCommuneCity($city);
    }

    /**
     * Moyens de paiement acceptés pour cette adresse.
     *
     * Un tableau vide signifie « rien à choisir au checkout » : international
     * (finalisation sur WhatsApp) ou hors zone (nos agents fixent le règlement).
     *
     * @return string[]
     */
    public static function allowed(?array $address): array
    {
        $destination = self::destination($address);

        if ($destination === self::INTERNATIONAL || self::isOutOfZone($address)) {
            return [];
        }

        return $destination === self::ABIDJAN
            ? ['cod', 'delivery']
            : ['wave', 'orange_money'];
    }

    /** Le moyen de paiement est-il autorisé pour cette adresse ? */
    public static function accepts(?array $address, ?string $method): bool
    {
        return in_array((string) $method, self::allowed($address), true);
    }

    /**
     * Message d'erreur destiné à la cliente lorsqu'un moyen est refusé.
     * Formulé côté métier plutôt que technique : il s'affiche tel quel.
     */
    public static function refusalMessage(?array $address): string
    {
        if (self::destination($address) === self::INTERNATIONAL) {
            return "Pour une livraison hors de Côte d'Ivoire, la commande se finalise avec un de nos agents sur WhatsApp.";
        }

        if (self::isOutOfZone($address)) {
            return "Votre zone de livraison n'est pas encore tarifée : nos agents vous contactent pour les frais et le mode de règlement.";
        }

        return self::destination($address) === self::ABIDJAN
            ? 'À ' . HomeZone::label() . ', le règlement se fait à la livraison.'
            : "Pour une livraison à l'intérieur du pays, la commande doit être réglée d'avance par Wave ou Orange Money.";
    }
}
