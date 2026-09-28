<?php

namespace Modules\Coupon\Support;

use Illuminate\Support\Collection;
use Modules\Admin\Models\Setting;
use Modules\Cart\Models\Cart;
use Modules\Coupon\Models\Coupon;

/**
 * Assiette et montant d'un code promo.
 *
 * Règle unique, partagée par le panier, le tunnel classique, la commande rapide
 * et la création de commande. Elle existait auparavant en deux exemplaires qui
 * ne disaient pas la même chose : le panier remisait tout le sous-total, la
 * commande écartait les articles déjà en promotion. La cliente voyait donc au
 * panier une réduction plus forte que celle qui lui était facturée.
 *
 * Deux restrictions s'enchaînent sur l'assiette :
 *
 *  1. Ciblage — un coupon rattaché à des articles ne mord que sur eux. C'est
 *     ce qui permet d'écouler une référence sans brader la commande entière.
 *  2. Cumul — par défaut le code ne s'applique pas à un article déjà remisé par
 *     une opération : −30 % puis −20 % vendrait à −44 % sans que personne l'ait
 *     décidé. Le réglage `promo_allow_stacking` autorise explicitement le cumul.
 */
final class CouponDiscount
{
    /**
     * Assiette et remise pour un panier en base.
     *
     * @return array{base: float, discount: float}
     */
    public static function forCart(Coupon $coupon, Cart $cart): array
    {
        return self::forLines($coupon, $cart->items->map(fn ($item) => [
            'product'    => $item->product,
            'variant'    => $item->variant,
            'unit_price' => (float) $item->unit_price,
            'quantity'   => (int) $item->quantity,
        ]));
    }

    /**
     * Assiette et remise pour des lignes déjà résolues.
     *
     * La commande rapide n'a pas de panier en base : elle passe ses lignes
     * telles que `resolvePayloadItems` les a construites. Même forme, même
     * règle — c'est tout l'intérêt de ne l'écrire qu'une fois.
     *
     * @param  iterable<array{product: mixed, variant: mixed, unit_price: float, quantity: int}>  $lines
     * @return array{base: float, discount: float}
     */
    public static function forLines(Coupon $coupon, iterable $lines): array
    {
        $cibles = $coupon->targetedProductIds();

        $base = Collection::make($lines)
            ->filter(fn (array $line) => self::isEligible($line, $cibles))
            ->sum(fn (array $line) => $line['unit_price'] * $line['quantity']);

        $base = round((float) $base, 2);

        if ($base <= 0) {
            return ['base' => 0.0, 'discount' => 0.0];
        }

        return ['base' => $base, 'discount' => $coupon->computeDiscount($base)];
    }

    /** @param  int[]  $cibles */
    private static function isEligible(array $line, array $cibles): bool
    {
        $product = $line['product'] ?? null;

        // Coupon ciblé : tout ce qui n'est pas dans la liste reste au prix fort.
        if ($cibles !== [] && ! in_array($product?->id, $cibles, true)) {
            return false;
        }

        if ((bool) Setting::get('promo_allow_stacking', false)) {
            return true;
        }

        // Un article vendu au tarif de sa variante ne suit pas le prix
        // promotionnel du produit : il reste éligible au coupon.
        $suitLaVariante = (float) ($line['variant']?->price ?? 0) > 0;

        return $suitLaVariante || ! ($product?->is_discounted ?? false);
    }
}
