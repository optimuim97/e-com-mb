<?php

namespace Modules\Order\Services\OrderCreation;

use Modules\Cart\Models\Cart;
use Modules\Coupon\Models\Coupon;
use Modules\Coupon\Support\CouponDiscount;

class DiscountCalculator
{
    /**
     * Remise liée au coupon saisi par la cliente.
     *
     * Les promotions automatiques sont déjà déduites en amont, dans le prix
     * effectif de chaque article. Ce calcul ne porte donc que sur le coupon.
     *
     * Par défaut le coupon ne s'applique **pas** aux articles déjà en
     * promotion : sans cette règle, une opération à −30 % suivie d'un code
     * à −20 % vendrait à −44 % sans que personne l'ait décidé. Le réglage
     * `promo_allow_stacking` autorise explicitement le cumul.
     *
     * @return array{discount: float, coupon: Coupon|null}
     */
    public function calculateDiscount(Cart $cart, float $subtotal): array
    {
        if (! $cart->coupon_code) {
            return ['discount' => 0.0, 'coupon' => null];
        }

        $coupon = Coupon::where('code', $cart->coupon_code)->first();

        if (! $coupon || ! $coupon->isValid($subtotal)) {
            return ['discount' => 0.0, 'coupon' => null];
        }

        // Un coupon nominatif n'a de valeur que pour sa destinataire.
        if ($coupon->customer_id && $coupon->customer_id !== $cart->user_id) {
            return ['discount' => 0.0, 'coupon' => null];
        }

        // Assiette et montant viennent de CouponDiscount : le panier affiche
        // désormais exactement ce qui sera facturé ici, ciblage compris.
        ['discount' => $discount] = CouponDiscount::forCart($coupon, $cart);

        if ($discount <= 0) {
            return ['discount' => 0.0, 'coupon' => null];
        }

        $coupon->increment('used_count');

        return ['discount' => $discount, 'coupon' => $coupon];
    }
}
