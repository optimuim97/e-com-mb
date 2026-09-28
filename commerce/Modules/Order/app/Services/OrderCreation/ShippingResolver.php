<?php

namespace Modules\Order\Services\OrderCreation;

use Modules\Delivery\Models\DeliveryZone;

/**
 * Résout les frais de livraison d'une commande côté serveur, depuis les
 * zones de livraison configurées dans l'admin — source de vérité unique.
 *
 * Règles métier :
 *  - Le prix de la zone s'applique toujours (aucune livraison gratuite).
 *  - Adresse abidjanaise hors zone → tarif de base d'Abidjan (réglable en
 *    admin) : on sait livrer, on ne bloque donc pas l'expédition.
 *  - Zone introuvable ailleurs → frais inconnus : la commande est marquée
 *    `shipping_unknown` et les agents renseignent les frais manuellement.
 *  - Tarif au kilo (international) → le poids n'est pas connu à la commande :
 *    même traitement « à renseigner par nos agents ».
 */
class ShippingResolver
{
    /**
     * @param  array  $shippingAddress  adresse de livraison (toArray de AddressData)
     * @return array{cost: float, unknown: bool, zone_id: ?int, zone_name: ?string}
     */
    public function resolve(array $shippingAddress): array
    {
        $quote = DeliveryZone::quoteWithAbidjanBase(
            (string) ($shippingAddress['city'] ?? ''),
            (string) ($shippingAddress['country'] ?? 'CI'),
            null,
            $shippingAddress['commune'] ?? null,
        );

        // Zone non identifiée, ou tarif au kilo (poids inconnu à ce stade)
        if (!$quote || ($quote['unit'] ?? 'fixed') === 'per_kg') {
            return [
                'cost'      => 0.0,
                'unknown'   => true,
                'zone_id'   => $quote['zone_id']   ?? null,
                'zone_name' => $quote['zone_name'] ?? null,
            ];
        }

        return [
            'cost'      => (float) $quote['price'],
            'unknown'   => false,
            'zone_id'   => $quote['zone_id'],
            'zone_name' => $quote['zone_name'],
        ];
    }
}
