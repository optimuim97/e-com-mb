<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Models\Setting;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Catalog\Models\Product;
use Modules\Coupon\Models\Coupon;
use Modules\Order\Services\OrderCreation\DiscountCalculator;
use Modules\Promo\Models\Promotion;
use Tests\TestCase;

/**
 * Rencontre entre une promotion automatique et un code promo.
 *
 * Règle retenue : le coupon ne mord que sur les articles qui ne sont pas
 * déjà remisés, sauf si le cumul est explicitement autorisé.
 */
class PromotionCouponInteractionTest extends TestCase
{
    use RefreshDatabase;

    private function cartWith(array $products, string $code, ?User $customer = null): Cart
    {
        $customer ??= User::factory()->create();
        $cart = Cart::create(['user_id' => $customer->id, 'coupon_code' => $code]);

        foreach ($products as $p) {
            CartItem::create([
                'cart_id'    => $cart->id,
                'product_id' => $p->id,
                'quantity'   => 1,
            ]);
        }

        return $cart->fresh(['items.product', 'items.variant']);
    }

    private function coupon(array $attrs = []): Coupon
    {
        return Coupon::create(array_merge([
            'code'      => 'PROMO10',
            'type'      => 'percentage',
            'value'     => 10,
            'is_active' => true,
        ], $attrs));
    }

    private function globalPromotion(float $value = 30): Promotion
    {
        return Promotion::create([
            'name' => 'Soldes', 'scope' => 'global', 'type' => 'standard',
            'discount_type' => 'percent', 'value' => $value, 'is_active' => true,
        ]);
    }

    public function test_sans_promotion_le_coupon_porte_sur_tout_le_panier(): void
    {
        $this->coupon();
        $produit = Product::factory()->create(['price' => 10000]);
        $cart = $this->cartWith([$produit], 'PROMO10');

        $r = app(DiscountCalculator::class)->calculateDiscount($cart, $cart->subtotal);

        $this->assertSame(1000.0, $r['discount']);
    }

    public function test_le_coupon_ignore_un_article_deja_en_promotion(): void
    {
        // Sans cette règle : 30 % puis 10 % reviendrait à vendre à −37 %.
        $this->globalPromotion(30);
        $this->coupon();
        $produit = Product::factory()->create(['price' => 10000]);
        $cart = $this->cartWith([$produit], 'PROMO10');

        $r = app(DiscountCalculator::class)->calculateDiscount($cart, $cart->subtotal);

        $this->assertSame(0.0, $r['discount']);
        $this->assertNull($r['coupon']);
    }

    public function test_le_coupon_porte_sur_les_seuls_articles_hors_promotion(): void
    {
        $discounted = Product::factory()->create(['price' => 10000]);
        $regular  = Product::factory()->create(['price' => 20000]);

        $promotion = Promotion::create([
            'name' => 'Ciblee', 'scope' => 'product', 'type' => 'standard',
            'discount_type' => 'percent', 'value' => 50, 'is_active' => true,
        ]);
        $promotion->products()->attach($discounted->id);
        $this->coupon();

        $cart = $this->cartWith([$discounted, $regular], 'PROMO10');
        $r = app(DiscountCalculator::class)->calculateDiscount($cart, $cart->subtotal);

        // 10 % des 20 000 non remisés, pas des 25 000 du panier entier.
        $this->assertSame(2000.0, $r['discount']);
    }

    public function test_le_cumul_peut_etre_autorise_par_reglage(): void
    {
        Setting::set('promo_allow_stacking', true);
        $this->globalPromotion(30);
        $this->coupon();
        $produit = Product::factory()->create(['price' => 10000]);

        $cart = $this->cartWith([$produit], 'PROMO10');
        $r = app(DiscountCalculator::class)->calculateDiscount($cart, $cart->subtotal);

        // Sous-total déjà remisé à 7000, le coupon en retire 10 %.
        $this->assertSame(700.0, $r['discount']);
    }

    public function test_coupon_nominatif_refuse_pour_une_autre_cliente(): void
    {
        $recipient = User::factory()->create();
        $other        = User::factory()->create();
        $this->coupon(['customer_id' => $recipient->id]);

        $produit = Product::factory()->create(['price' => 10000]);
        $cart = $this->cartWith([$produit], 'PROMO10', $other);

        $r = app(DiscountCalculator::class)->calculateDiscount($cart, $cart->subtotal);

        $this->assertSame(0.0, $r['discount']);
    }

    public function test_coupon_nominatif_accepte_pour_sa_destinataire(): void
    {
        $recipient = User::factory()->create();
        $this->coupon(['customer_id' => $recipient->id]);

        $produit = Product::factory()->create(['price' => 10000]);
        $cart = $this->cartWith([$produit], 'PROMO10', $recipient);

        $r = app(DiscountCalculator::class)->calculateDiscount($cart, $cart->subtotal);

        $this->assertSame(1000.0, $r['discount']);
    }

    public function test_le_compteur_d_usage_ne_bouge_pas_si_la_remise_est_refusee(): void
    {
        // Un coupon rejeté ne doit pas consommer un usage : la cliente
        // retrouverait un code épuisé sans en avoir jamais profité.
        $this->globalPromotion(30);
        $coupon = $this->coupon(['usage_limit' => 1]);
        $produit = Product::factory()->create(['price' => 10000]);
        $cart = $this->cartWith([$produit], 'PROMO10');

        app(DiscountCalculator::class)->calculateDiscount($cart, $cart->subtotal);

        $this->assertSame(0, $coupon->fresh()->used_count);
    }
}
