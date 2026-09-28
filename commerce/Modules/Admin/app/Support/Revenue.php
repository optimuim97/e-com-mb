<?php

namespace Modules\Admin\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Order\Models\Order;

/**
 * Règle unique du chiffre d'affaires.
 *
 * Trois définitions coexistaient et donnaient trois chiffres différents pour la
 * même journée : le total ne comptait que `delivered` et `shipped`, le CA du
 * jour tout sauf les annulées — donc les commandes encore en attente et les
 * remboursées —, et la courbe sur trente jours n'excluait rien du tout. Une
 * commande annulée gonflait donc la courbe, et une commande jamais confirmée le
 * CA du jour.
 *
 * Deux notions, et deux seulement :
 *
 * - le CA **réel**, c'est ce qui est arrivé chez la cliente. Une commande
 *   livrée ne se rétracte plus ; c'est le seul montant sur lequel on peut
 *   compter.
 * - le CA **en cours**, c'est ce qui est engagé mais pas encore chez elle.
 *   Affiché à part : le confondre avec le réel, c'est se féliciter d'argent
 *   qu'un refus à la porte peut encore effacer.
 *
 * Annulées et remboursées ne comptent nulle part. Les commandes `pending` non
 * plus : tant que la boutique n'a pas confirmé, rien n'est engagé.
 *
 * Montant retenu : les articles après remise, **hors frais de livraison**. Ces
 * frais sont reversés au livreur — les compter reviendrait à s'attribuer un
 * chiffre qu'on ne garde pas.
 */
final class Revenue
{
    /** Marchandise livrée : le chiffre d'affaires est acquis. */
    public const DELIVERED = ['delivered'];

    /** Engagé, pas encore livré. */
    public const IN_PROGRESS = ['confirmed', 'processing', 'shipped'];

    /**
     * Montant retenu par commande, en SQL.
     *
     * `subtotal` porte les articles, `discount_amount` la remise du coupon.
     * `shipping_cost` est volontairement absent.
     */
    public static function amount(): string
    {
        return 'orders.subtotal - orders.discount_amount';
    }

    /** Commandes dont le chiffre d'affaires est acquis. */
    public static function delivered(): Builder
    {
        return Order::query()->whereIn('status', self::DELIVERED);
    }

    /** Commandes engagées mais pas encore livrées. */
    public static function inProgress(): Builder
    {
        return Order::query()->whereIn('status', self::IN_PROGRESS);
    }

    /** Somme du chiffre d'affaires sur une requête déjà filtrée. */
    public static function sum(Builder $query): float
    {
        return (float) $query->sum(DB::raw(self::amount()));
    }

    /**
     * Date à laquelle le chiffre d'affaires est réalisé.
     *
     * La livraison, pas la commande. Sans cela, la semaine en cours afficherait
     * toujours zéro — rien de ce qui vient d'être commandé n'est encore livré —
     * et le tableau de bord serait illisible le lundi matin.
     *
     * `delivered_at` n'existait pas au début de l'exploitation : les commandes
     * livrées d'avant retombent sur leur date de création, faute de mieux.
     */
    public static function realisedAt(): string
    {
        return 'COALESCE(orders.delivered_at, orders.created_at)';
    }
}
