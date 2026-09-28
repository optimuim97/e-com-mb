<?php

namespace Modules\Promo\Support;

use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Promo\Services\PromotionResolver;

/**
 * Prix de vente d'un article, promotions comprises.
 *
 * Règle unique du montant facturé. Elle existait en quatre exemplaires — panier,
 * commande depuis le panier, commande invitée, caisse — et trois d'entre eux
 * avaient dérivé sur `products.price` : la cliente voyait le prix remisé dans
 * son panier puis se retrouvait facturée au tarif plein. Sur le parcours invité,
 * c'est la commande elle-même qui était enregistrée trop cher, donc aussi le
 * chiffre d'affaires.
 */
final class SellingPrice
{
    /**
     * Une variante tarifée impose son prix : c'est une déclinaison vendue à son
     * propre tarif, que le résolveur de promotions ne couvre pas. Un prix nul ou
     * absent n'est pas un tarif — on retombe alors sur celui du produit, remise
     * comprise.
     */
    public static function for(?Product $product, ?ProductVariant $variant = null): float
    {
        return self::resolve($product, $variant)['price'];
    }

    /**
     * Prix de vente, et ce qui l'explique.
     *
     * La ligne de commande a besoin des trois : ce qu'elle facture, quelle
     * opération l'a fait baisser, et de combien. Sans cette attribution écrite
     * au moment de la vente, aucun calcul ultérieur ne peut la retrouver — le
     * barème aura changé.
     *
     * `promotion_id` n'est renseigné que si la promotion **est** la raison du
     * prix. Une vente flash plus avantageuse l'emporte et la ligne ne lui est
     * alors pas attribuée : compter cette vente au crédit de l'opération
     * gonflerait son résultat d'une remise qu'elle n'a pas accordée.
     *
     * @return array{price: float, promotion_id: int|null, discount: float}
     */
    public static function resolve(?Product $product, ?ProductVariant $variant = null): array
    {
        $variantPrice = (float) ($variant?->price ?? 0);

        // Une variante tarifée impose son prix : le résolveur de promotions ne
        // la couvre pas, il n'y a donc rien à attribuer.
        if ($variantPrice > 0) {
            return ['price' => $variantPrice, 'promotion_id' => null, 'discount' => 0.0];
        }

        if (! $product) {
            return ['price' => 0.0, 'promotion_id' => null, 'discount' => 0.0];
        }

        $base  = (float) $product->price;
        $price = (float) ($product->effective_price ?? $base);
        $offre = app(PromotionResolver::class)->bestOfferFor($product);

        // Au centime près : le prix effectif est arrondi, celui de l'offre non.
        $promotionGagne = $offre !== null && round($offre['price'], 2) <= round($price, 2);

        return [
            'price'        => $price,
            'promotion_id' => $promotionGagne ? $offre['promotion']->id : null,
            'discount'     => max(0.0, round($base - $price, 2)),
        ];
    }
}
