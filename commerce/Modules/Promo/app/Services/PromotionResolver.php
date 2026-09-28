<?php

namespace Modules\Promo\Services;

use Illuminate\Support\Collection;
use Modules\Catalog\Models\Product;
use Modules\Promo\Models\Promotion;

/**
 * Détermine la promotion applicable à un produit.
 *
 * Les promotions ne se cumulent pas entre elles : une seule s'applique, celle
 * de plus haute priorité, et à priorité égale la plus avantageuse pour la
 * cliente. Deux remises qui s'empilent finiraient par vendre à perte sans que
 * personne ne s'en aperçoive.
 *
 * Enregistré en singleton de requête : les promotions actives sont lues une
 * fois, puis résolues en mémoire. Sans cela, afficher un catalogue de 24
 * produits déclencherait 24 requêtes supplémentaires.
 */
class PromotionResolver
{
    /** @var Collection<int, Promotion>|null */
    private ?Collection $globalPromotions = null;

    /**
     * Promotions ciblées, indexées par product_id. Chaque entrée retient la
     * remise dérogatoire éventuelle, lue une seule fois depuis le pivot.
     *
     * @var Collection<int, Collection<int, array{promotion: Promotion, discount_type: ?string, value: ?string}>>|null
     */
    private ?Collection $promotionsByProduct = null;

    /** La promotion qui s'applique à ce produit, ou null. */
    public function bestFor(Product $product): ?Promotion
    {
        return $this->bestEntryFor($product)['promotion'] ?? null;
    }

    /**
     * Promotion retenue et remise effectivement appliquée à ce produit.
     *
     * @return array{promotion: Promotion, price: float}|array{}
     */
    private function bestEntryFor(Product $product): array
    {
        $this->load();

        $price = (float) $product->price;

        // Une promotion globale s'applique telle quelle ; une promotion ciblée
        // peut porter une remise propre à cet article.
        $candidates = $this->globalPromotions
            ->map(fn (Promotion $p) => [
                'promotion' => $p,
                'price'     => $p->priceFor($price),
            ])
            ->concat(
                ($this->promotionsByProduct->get($product->id) ?? collect())
                    ->map(fn (array $row) => [
                        'promotion' => $row['promotion'],
                        'price'     => $row['promotion']->priceFor(
                            $price, $row['discount_type'], $row['value']
                        ),
                    ])
            );

        if ($candidates->isEmpty()) {
            return [];
        }

        // Priorité d'abord, puis la remise la plus avantageuse.
        return $candidates
            ->sortByDesc(fn (array $c) => [$c['promotion']->priority, $price - $c['price']])
            ->first();
    }

    /**
     * Promotion retenue pour ce produit et le prix qu'elle donne.
     *
     * Expose l'arbitrage que `bestFor` réduit à la seule promotion. Les
     * statistiques en ont besoin : attribuer une vente à une opération suppose
     * de vérifier que c'est bien elle qui a fixé le prix, et non une vente
     * flash qui l'aurait battue.
     *
     * @return array{promotion: Promotion, price: float}|null
     */
    public function bestOfferFor(Product $product): ?array
    {
        $best = $this->bestEntryFor($product);

        return $best === [] ? null : $best;
    }

    /**
     * Prix effectif, promotions et vente flash confondues.
     *
     * La vente flash est une promotion comme une autre : on retient le prix le
     * plus bas des deux plutôt que d'en privilégier une arbitrairement,
     * conformément à la règle « une seule remise, la meilleure ».
     */
    public function priceFor(Product $product): float
    {
        $price = (float) $product->price;

        if ($product->is_flash_active && $product->flash_price) {
            $price = min($price, (float) $product->flash_price);
        }

        $best = $this->bestEntryFor($product);
        if ($best !== []) {
            $price = min($price, $best['price']);
        }

        return round($price, 2);
    }

    /** Le produit bénéficie-t-il déjà d'une remise ? */
    public function isDiscounted(Product $product): bool
    {
        return $this->priceFor($product) < (float) $product->price;
    }

    /**
     * Précharge les promotions actives.
     *
     * À appeler avant de boucler sur un catalogue : sans cela la première
     * résolution charge tout, ce qui reste correct mais moins explicite.
     */
    public function warm(): void
    {
        $this->load();
    }

    /** Vide le cache mémoire — utile après création ou modification. */
    public function forget(): void
    {
        $this->globalPromotions = null;
        $this->promotionsByProduct = null;
    }

    private function load(): void
    {
        if ($this->globalPromotions !== null) {
            return;
        }

        $active = Promotion::activeNow()->with('products:id')->get();

        $this->globalPromotions = $active->where('scope', Promotion::SCOPE_GLOBAL)->values();

        $this->promotionsByProduct = $active
            ->where('scope', Promotion::SCOPE_PRODUCT)
            ->flatMap(fn (Promotion $promotion) => $promotion->products->map(
                fn ($product) => [
                    'product_id'    => $product->id,
                    'promotion'     => $promotion,
                    // Lu ici plutôt qu'à chaque résolution : le pivot n'est
                    // disponible que sur l'instance chargée par la relation.
                    'discount_type' => $product->pivot->discount_type,
                    'value'         => $product->pivot->value,
                ]
            ))
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->values());
    }
}
