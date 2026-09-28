<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Catalog\Models\Product;
use Modules\Coupon\Models\Coupon;
use Modules\Order\Models\Order;
use Tests\Concerns\ActingAsUser;
use Tests\TestCase;

/**
 * Code promo ciblé : le code ne remise que ses articles.
 *
 * Sert à écouler une référence précise sans brader le reste de la commande. Un
 * coupon sans article rattaché garde son comportement d'avant : il porte sur
 * tout le panier.
 *
 * La règle vit dans CouponDiscount et doit donner le même montant partout — le
 * panier l'affichait auparavant sur la totalité du sous-total alors que la
 * commande l'écartait des articles déjà en promotion, et la cliente était donc
 * facturée moins que ce qu'on lui avait montré.
 */
class TargetedCouponTest extends TestCase
{
    use RefreshDatabase, ActingAsUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUser();
    }

    private function coupon(array $products = []): Coupon
    {
        $coupon = Coupon::create([
            'code' => 'ROSA10', 'type' => 'percentage', 'value' => 10, 'is_active' => true,
        ]);

        if ($products !== []) {
            $coupon->products()->sync(collect($products)->pluck('id')->all());
        }

        return $coupon;
    }

    private function panier(array $lignes): Cart
    {
        $cart = Cart::factory()->create(['user_id' => $this->user->id]);

        foreach ($lignes as [$product, $qte]) {
            CartItem::create([
                'cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $qte,
            ]);
        }

        return $cart->fresh(['items.product', 'items.variant']);
    }

    // ── Panier ──────────────────────────────────────────────────────────────

    public function test_la_remise_ne_porte_que_sur_larticle_cible(): void
    {
        $cible = Product::factory()->priced(10000)->create();
        $autre = Product::factory()->priced(5000)->create();

        $this->coupon([$cible]);
        $this->panier([[$cible, 1], [$autre, 1]]);

        // 10 % de 10 000 seulement : les 5 000 de l'autre article restent pleins.
        $this->asUser()->postJson('/api/cart/coupon', ['code' => 'ROSA10'])->assertOk();

        // Comparaison numérique : l'API sérialise 1000.0 en `1000`, et une
        // égalité stricte ferait échouer le test sur le type, pas le montant.
        $panier = $this->asUser()->getJson('/api/cart')->assertOk()->json();

        $this->assertEquals(1000, $panier['discount']);
        $this->assertEquals(15000, $panier['subtotal']);
        $this->assertEquals(14000, $panier['total']);
    }

    public function test_un_coupon_sans_cible_porte_sur_tout_le_panier(): void
    {
        $a = Product::factory()->priced(10000)->create();
        $b = Product::factory()->priced(5000)->create();

        $this->coupon();
        $this->panier([[$a, 1], [$b, 1]]);

        $this->asUser()->postJson('/api/cart/coupon', ['code' => 'ROSA10'])->assertOk();

        $this->assertEquals(
            1500,
            $this->asUser()->getJson('/api/cart')->assertOk()->json('discount'),
        );
    }

    public function test_un_code_cible_absent_du_panier_est_refuse(): void
    {
        $cible = Product::factory()->priced(10000)->create();
        $autre = Product::factory()->priced(5000)->create();

        $this->coupon([$cible]);
        $this->panier([[$autre, 1]]);

        // Accepté en silence, il se serait affiché « appliqué » pour une remise
        // nulle — le pire des deux.
        $this->asUser()
            ->postJson('/api/cart/coupon', ['code' => 'ROSA10'])
            ->assertStatus(422);
    }

    // ── Facturation ─────────────────────────────────────────────────────────

    public function test_la_commande_facture_la_meme_remise_que_le_panier(): void
    {
        $cible = Product::factory()->priced(10000)->create();
        $autre = Product::factory()->priced(5000)->create();

        $this->coupon([$cible]);
        $this->panier([[$cible, 1], [$autre, 1]]);

        $this->asUser()->postJson('/api/cart/coupon', ['code' => 'ROSA10'])->assertOk();

        $numero = $this->asUser()
            ->postJson('/api/orders', [
                'shipping_address' => [
                    'first_name' => 'Fatou', 'address_line1' => 'Cocody',
                    'city' => 'Abidjan', 'commune' => 'Cocody',
                    'country' => 'CI', 'phone' => '0102030405',
                ],
                'payment_method' => 'cod',
            ])
            ->assertCreated()
            ->json('number');

        $this->assertSame('1000.00', Order::where('number', $numero)->value('discount_amount'));
    }

    // ── Validation publique ─────────────────────────────────────────────────

    public function test_la_validation_chiffre_la_remise_sur_les_lignes_fournies(): void
    {
        $cible = Product::factory()->priced(10000)->create();
        $autre = Product::factory()->priced(5000)->create();

        $this->coupon([$cible]);

        $reponse = $this->postJson('/api/coupons/validate', [
            'code'  => 'ROSA10',
            'items' => [
                ['product_id' => $cible->id, 'quantity' => 1],
                ['product_id' => $autre->id, 'quantity' => 1],
            ],
        ])->assertOk()->assertJsonPath('targeted', true)->json();

        $this->assertEquals(1000, $reponse['discount_amount']);
        $this->assertEquals(10000, $reponse['eligible_base']);
    }

    public function test_la_validation_refuse_un_code_sans_effet(): void
    {
        $cible = Product::factory()->priced(10000)->create();
        $autre = Product::factory()->priced(5000)->create();

        $this->coupon([$cible]);

        $this->postJson('/api/coupons/validate', [
            'code'  => 'ROSA10',
            'items' => [['product_id' => $autre->id, 'quantity' => 1]],
        ])->assertStatus(422);
    }

    // ── Commande rapide ─────────────────────────────────────────────────────

    public function test_la_commande_rapide_applique_le_code(): void
    {
        $this->seed(\Modules\Delivery\Database\Seeders\DeliveryZoneSeeder::class);

        $cible = Product::factory()->priced(10000)->create();
        $autre = Product::factory()->priced(5000)->create();

        $this->coupon([$cible]);

        $numero = $this->postJson('/api/quick-order', [
            'name'           => 'Fatou Konaté',
            'phone'          => '0709080706',
            'commune'        => 'Cocody',
            'payment_method' => 'cod',
            'coupon_code'    => 'ROSA10',
            'items'          => [
                ['product_id' => $cible->id, 'quantity' => 1],
                ['product_id' => $autre->id, 'quantity' => 1],
            ],
        ])->assertCreated()->json('number');

        $order = Order::where('number', $numero)->firstOrFail();

        $this->assertSame('1000.00', $order->discount_amount);
        $this->assertSame('ROSA10', $order->coupon_code);
    }

    public function test_un_code_sans_effet_ne_fait_pas_echouer_la_commande_rapide(): void
    {
        $this->seed(\Modules\Delivery\Database\Seeders\DeliveryZoneSeeder::class);

        $cible = Product::factory()->priced(10000)->create();
        $autre = Product::factory()->priced(5000)->create();

        $this->coupon([$cible]);

        // Le refus explicite a déjà eu lieu à la saisie. Perdre la vente ici
        // pour un code devenu inapplicable serait le pire des deux.
        $numero = $this->postJson('/api/quick-order', [
            'name'           => 'Fatou Konaté',
            'phone'          => '0709080706',
            'commune'        => 'Cocody',
            'payment_method' => 'cod',
            'coupon_code'    => 'ROSA10',
            'items'          => [['product_id' => $autre->id, 'quantity' => 1]],
        ])->assertCreated()->json('number');

        $order = Order::where('number', $numero)->firstOrFail();

        $this->assertSame('0.00', $order->discount_amount);
        $this->assertNull($order->coupon_code);
    }
}
