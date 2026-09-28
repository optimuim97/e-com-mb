<?php

namespace Modules\Catalog\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\StockMovement;
use Modules\Catalog\Models\WeeklyStockAllocation;
use Modules\Order\Models\OrderItem;

/**
 * Le tableau de la semaine : une ligne par article, tout ce qu'il faut pour
 * décider en un coup d'œil.
 *
 * Une seule colonne est saisie — l'allocation. Les autres sont calculées, et
 * c'est délibéré : un compteur de « vendu » qu'il faudrait décrémenter à
 * chaque commande et recréditer à chaque annulation finirait par diverger du
 * réel, sans que rien ne le signale. Ici, le vendu se lit dans les lignes de
 * commande, l'entré dans le registre des mouvements. Les deux sources font
 * déjà foi ailleurs.
 */
class WeeklyStockBoard
{
    /** Ce qui ne compte pas comme une vente. */
    private const VOIDED = ['cancelled', 'refunded'];

    /**
     * @return array{week_start: string, week_end: string, rows: array<int, array<string, mixed>>}
     */
    public function forWeek(Carbon $weekStart): array
    {
        $debut = $weekStart->copy()->startOfWeek()->startOfDay();
        $fin   = $debut->copy()->endOfWeek()->endOfDay();

        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'stock', 'low_stock_threshold', 'track_stock', 'has_variants']);

        $allocations = WeeklyStockAllocation::query()
            ->forWeek($debut)
            ->get()
            ->keyBy('product_id');

        $vendus  = $this->soldBetween($debut, $fin);
        $entrees = $this->receivedBetween($debut, $fin);

        $rows = $products->map(function (Product $product) use ($allocations, $vendus, $entrees) {
            $allocation = $allocations->get($product->id);
            $alloue     = $allocation?->quantity;
            $vendu      = (int) ($vendus[$product->id] ?? 0);
            $entre      = (int) ($entrees[$product->id] ?? 0);

            return [
                'product_id' => $product->id,
                'name'       => $product->name,
                'sku'        => $product->sku,

                // Décidé par la boutique. Nul = aucun plafond cette semaine.
                'allocated'  => $alloue,
                'note'       => $allocation?->note,

                // Constaté.
                'sold'       => $vendu,
                'received'   => $entre,
                'stock'      => (int) $product->stock,

                // Ce qu'il reste à vendre en ligne. Nul quand rien n'est
                // alloué : il n'y a pas de reste à une quantité inexistante.
                'remaining'  => $alloue === null ? null : max(0, $alloue - $vendu),

                // Le plafond réellement appliqué : on ne vend jamais plus que
                // ce qu'on a, même si l'allocation le permettait.
                'sellable'   => $alloue === null
                    ? (int) $product->stock
                    : min((int) $product->stock, max(0, $alloue - $vendu)),

                'low_stock'  => $product->track_stock
                    && (int) $product->stock <= (int) $product->low_stock_threshold,
            ];
        });

        return [
            'week_start' => $debut->toDateString(),
            'week_end'   => $fin->toDateString(),
            'rows'       => $rows->values()->all(),
        ];
    }

    /**
     * Ce qu'il reste à vendre en ligne pour cet article cette semaine.
     *
     * `null` signifie « aucun plafond » : la boutique n'a rien alloué, la vente
     * reste bornée par le seul stock physique. C'est le comportement d'avant
     * cette fonctionnalité, et le repli voulu — un lundi férié ou une saisie
     * oubliée ne doit pas fermer la boutique.
     *
     * Zéro, en revanche, est une décision : l'article ne se vend pas en ligne
     * cette semaine.
     */
    public function remainingFor(int $productId, ?Carbon $week = null, ?int $exceptOrderId = null): ?int
    {
        $debut = ($week ?? now())->copy()->startOfWeek()->startOfDay();

        $allocation = WeeklyStockAllocation::lookup($productId, null, $debut);

        if (! $allocation) {
            return null;
        }

        $vendu = (int) ($this->soldBetween(
            $debut,
            $debut->copy()->endOfWeek()->endOfDay(),
            [$productId],
            $exceptOrderId,
        )[$productId] ?? 0);

        return max(0, $allocation->quantity - $vendu);
    }

    /**
     * Reste à vendre en ligne cette semaine, pour tous les articles alloués.
     *
     * Deux requêtes quel que soit le nombre d'articles : c'est ce qui permet
     * d'afficher la disponibilité sur un catalogue de soixante fiches sans
     * interroger la base soixante fois. Un article absent du tableau n'a pas
     * d'allocation — il n'est pas plafonné.
     *
     * @return array<int, int>  product_id => reste (0 = quota atteint)
     */
    public function remainingByProduct(?Carbon $week = null): array
    {
        $debut = ($week ?? now())->copy()->startOfWeek()->startOfDay();

        $allocations = WeeklyStockAllocation::query()
            ->forWeek($debut)
            ->whereNull('variant_id')
            ->pluck('quantity', 'product_id');

        if ($allocations->isEmpty()) {
            return [];
        }

        $vendus = $this->soldBetween(
            $debut,
            $debut->copy()->endOfWeek()->endOfDay(),
            $allocations->keys()->all(),
        );

        return $allocations
            ->map(fn ($quantite, $productId) => max(0, (int) $quantite - (int) ($vendus[$productId] ?? 0)))
            ->all();
    }

    /**
     * Quantités vendues sur la période, par article.
     *
     * Les commandes annulées et remboursées sont écartées : elles n'ont rien
     * consommé, et les compter bloquerait la vente d'articles disponibles.
     *
     * `$exceptOrderId` écarte la commande en cours d'écriture. Le parcours
     * panier crée ses lignes avant de contrôler le quota : sans cette
     * exception, la commande se comptait elle-même comme déjà vendue, et une
     * allocation de cinq refusait un panier de cinq.
     *
     * @param  array<int, int>|null  $productIds  restreint aux articles donnés
     * @return Collection<int, int>
     */
    private function soldBetween(Carbon $debut, Carbon $fin, ?array $productIds = null, ?int $exceptOrderId = null): Collection
    {
        return OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->whereNotIn('status', self::VOIDED)
                ->whereBetween('created_at', [$debut, $fin]))
            ->when($productIds !== null, fn ($q) => $q->whereIn('product_id', $productIds))
            ->when($exceptOrderId !== null, fn ($q) => $q->where('order_id', '!=', $exceptOrderId))
            ->selectRaw('product_id, SUM(quantity) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');
    }

    /**
     * Entrées de stock sur la période, par article.
     *
     * Seuls les mouvements positifs comptent : une sortie est déjà décrite par
     * le vendu, la compter ici la ferait apparaître deux fois.
     *
     * @return Collection<int, int>
     */
    private function receivedBetween(Carbon $debut, Carbon $fin): Collection
    {
        return StockMovement::query()
            ->whereBetween('created_at', [$debut, $fin])
            ->where('quantity', '>', 0)
            ->selectRaw('product_id, SUM(quantity) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');
    }
}
