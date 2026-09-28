<?php

namespace Modules\Order\Support;

/**
 * Ce qu'« réservé » veut dire dans cette boutique.
 *
 * Le stock est décompté à la CRÉATION de la commande, pas à sa confirmation :
 * `products.stock` est donc déjà net de tout ce qui est commandé. Une commande
 * confirmée n'a, en elle-même, aucun effet sur le compteur — la confirmation
 * est un état de traitement, pas un geste de stock.
 *
 * D'où la question que se pose la boutique devant un rayon : « il m'en reste
 * combien en vrai ? ». La réponse est
 *
 *     stock physique = compteur + réservé
 *
 * où le réservé est ce qui est vendu mais pas encore parti. Une fois la
 * commande expédiée, la marchandise a quitté la boutique : elle cesse d'être
 * réservée et le compteur seul dit la vérité.
 */
final class StockReservation
{
    /**
     * Statuts qui immobilisent de la marchandise encore présente en boutique.
     *
     * `shipped` et `delivered` en sont exclus : le colis est parti.
     * `cancelled` et `refunded` aussi : le stock est revenu.
     */
    public const HOLDING_STATUSES = ['pending', 'confirmed', 'processing'];
}
