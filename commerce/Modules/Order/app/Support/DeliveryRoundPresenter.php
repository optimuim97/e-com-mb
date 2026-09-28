<?php

namespace Modules\Order\Support;

use Modules\Order\Models\DeliveryRound;
use Modules\Order\Models\DeliveryRoundOrder;

/**
 * Mise en forme d'une tournée pour le JSON.
 *
 * Partagée par l'écran de la boutique et celui du livreur. Les deux affichent
 * le même pointage et le même compte : deux mises en forme parallèles auraient
 * fini par diverger, et c'est justement sur les montants qu'un désaccord entre
 * l'écran du livreur et celui de la caisse serait le plus coûteux.
 */
final class DeliveryRoundPresenter
{
    /** @return array<string, mixed> */
    public static function round(DeliveryRound $round, bool $detailed = false): array
    {
        $base = [
            'id'             => $round->id,
            'code'           => $round->code,
            'label'          => $round->label,
            'zone_group'     => $round->zone_group,
            'zone_name'      => $round->zone_name,
            'status'         => $round->status,
            'courier'        => $round->courier ? [
                'id'   => $round->courier->id,
                'name' => $round->courier->name,
            ] : null,
            'orders_count'   => $round->lines_count ?? $round->lines->count(),
            // Ce que le livreur emporte, en articles. Lu dans l'agrégat de la
            // liste quand il a tourné, recompté sur les lignes sinon.
            'items_count'    => (int) ($round->items_count
                ?? $round->lines->sum(fn (DeliveryRoundOrder $l) => self::articles($l))),
            'expected_total' => (float) ($round->lines_sum_amount_expected ?? $round->lines->sum('amount_expected')),
            'dispatched_at'  => $round->dispatched_at?->toIso8601String(),
            'closed_at'      => $round->closed_at?->toIso8601String(),
            'notes'          => $round->notes,
        ];

        if ($detailed) {
            $base['lines']        = $round->lines->map(fn (DeliveryRoundOrder $l) => self::line($l))->all();
            $base['summary']      = self::summary($round);
            $base['loading_list'] = self::loadingList($round);
        }

        return $base;
    }

    /** Nombre d'articles d'une livraison. */
    private static function articles(DeliveryRoundOrder $line): int
    {
        return (int) ($line->order?->items->sum('quantity') ?? 0);
    }

    /**
     * Le chargement, article par article : « 5 × Crème hydratante ».
     *
     * Un total seul dit combien emporter, pas quoi. C'est cette liste qu'on
     * pose à côté des colis pour vérifier que rien ne manque avant le départ —
     * et qu'on relit au retour quand un client dit ne pas avoir reçu son pot.
     *
     * Écran seulement : le récapitulatif par produit a été retiré de la feuille
     * papier à la demande de la boutique, il n'y revient pas par ce biais.
     *
     * @return array<int, array{name: string, quantity: int}>
     */
    public static function loadingList(DeliveryRound $round): array
    {
        $liste = [];

        foreach ($round->lines as $line) {
            foreach ($line->order?->items ?? [] as $item) {
                $nom = $item->product_name . ($item->variant_label ? ' (' . $item->variant_label . ')' : '');
                $liste[$nom] = ($liste[$nom] ?? 0) + (int) $item->quantity;
            }
        }

        arsort($liste);

        return collect($liste)
            ->map(fn (int $quantite, string $nom) => ['name' => $nom, 'quantity' => $quantite])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public static function line(DeliveryRoundOrder $line): array
    {
        $order = $line->order;
        $addr  = $order?->shipping_address ?? [];

        return [
            'id'               => $line->id,
            'position'         => $line->position,
            'status'           => $line->status,
            'amount_expected'  => (float) $line->amount_expected,
            'items_count'      => self::articles($line),
            'amount_collected' => $line->amount_collected === null ? null : (float) $line->amount_collected,
            'payment_method'   => $line->payment_method_collected,
            'failure_reason'   => $line->failure_reason,
            'delivered_at'     => $line->delivered_at?->toIso8601String(),
            'order' => $order ? [
                'id'       => $order->id,
                'number'   => $order->number,
                'status'   => $order->status,
                'total'    => (float) $order->total,
                'is_paid'  => (bool) $order->paid_at,
                'name'     => trim(($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? '')),
                'phone'    => $addr['phone'] ?? null,
                'locality' => $addr['commune'] ?? $addr['city'] ?? null,
                'address'  => $addr['address_line1'] ?? null,
                'landmark' => $addr['landmark'] ?? null,
                'note'     => $order->customer_note,
            ] : null,
        ];
    }

    /**
     * Le compte de la tournée : ce qui est passé, et l'écart de caisse.
     *
     * `difference` n'est calculée que sur les livraisons pointées. Y mêler les
     * lignes encore en attente ferait apparaître un manque qui n'est qu'un
     * pointage inachevé — et c'est cette ligne-là que la gérante regarde.
     *
     * @return array<string, mixed>
     */
    public static function summary(DeliveryRound $round): array
    {
        $parStatut = $round->lines->groupBy('status');
        $pointees  = $round->lines->where('status', '!=', DeliveryRoundOrder::STATUS_PENDING);

        $attendu  = (float) $pointees->sum('amount_expected');
        $encaisse = (float) $pointees->sum('amount_collected');

        return [
            'delivered' => $parStatut->get(DeliveryRoundOrder::STATUS_DELIVERED)?->count() ?? 0,
            'failed'    => $parStatut->get(DeliveryRoundOrder::STATUS_FAILED)?->count()    ?? 0,
            'postponed' => $parStatut->get(DeliveryRoundOrder::STATUS_POSTPONED)?->count() ?? 0,
            'pending'   => $parStatut->get(DeliveryRoundOrder::STATUS_PENDING)?->count()   ?? 0,
            'expected_on_pointed' => $attendu,
            'collected'           => $encaisse,
            'difference'          => $encaisse - $attendu,
        ];
    }
}
