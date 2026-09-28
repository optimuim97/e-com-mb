<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Catalog\Models\Product;
use Modules\Order\Models\Order;
use Modules\Promo\Models\Promotion;
use Tests\TestCase;

/**
 * Une promotion en cours doit être facturée, pas seulement annoncée.
 *
 * Les quatre chemins de vente calculaient le prix chacun de leur côté et trois
 * étaient restés sur `products.price` : la cliente voyait le tarif remisé sur
 * la boutique et se retrouvait facturée au prix plein. Sur le parcours invité,
 * la commande elle-même était enregistrée trop cher — donc le chiffre
 * d'affaires aussi.
 *
 * Chaque chemin est vérifié sur la ligne de commande **et** sur le total : un
 * en-tête juste au-dessus de lignes fausses passerait inaperçu jusqu'à la
 * facture.
 */
class PromotionBilledTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Delivery\Database\Seeders\DeliveryZoneSeeder::class);
    }

    /** Article à 5 000 F remisé de 15 % par une opération ciblée → 4 250 F. */
    private function articleEnPromotion(): Product
    {
        $product = Product::factory()->create(['price' => 5000, 'stock' => 50]);

        $promotion = Promotion::create([
            'name'          => 'Rosa Days',
            'scope'         => 'product',
            'type'          => 'standard',
            'discount_type' => 'percent',
            'value'         => 15,
            'is_active'     => true,
        ]);
        $promotion->products()->attach($product->id);

        return $product;
    }

    public function test_le_prix_effectif_est_bien_remise(): void
    {
        $this->assertSame(4250.0, $this->articleEnPromotion()->effective_price);
    }

    // ── Chemin 1 : panier en base d'une cliente connectée ───────────────────

    public function test_le_panier_en_base_facture_le_prix_remise(): void
    {
        $product  = $this->articleEnPromotion();
        $customer = User::factory()->create(['phone' => '0102030405']);

        $cart = Cart::create(['user_id' => $customer->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2]);

        Sanctum::actingAs($customer);

        $res = $this->postJson('/api/orders', [
            'shipping_address' => [
                'first_name'    => 'Fatou',
                'last_name'     => 'Konaté',
                'address_line1' => 'Rue du marché',
                'city'          => 'Abidjan',
                'commune'       => 'Cocody',
                'country'       => 'CI',
                'phone'         => '0102030405',
            ],
            'payment_method' => 'cod',
        ])->assertCreated();

        $order = Order::where('number', $res->json('number'))->firstOrFail();

        $this->assertSame('4250.00', $order->items->first()->unit_price);
        $this->assertSame('8500.00', $order->items->first()->total_price);
        $this->assertSame('8500.00', $order->subtotal);
    }

    // ── Chemin 2 : commande rapide (invitée, articles en charge utile) ──────

    public function test_la_commande_rapide_facture_le_prix_remise(): void
    {
        $product = $this->articleEnPromotion();

        $res = $this->postJson('/api/quick-order', [
            'name'           => 'Fatou Konaté',
            'phone'          => '0102030405',
            'commune'        => 'Cocody',
            'payment_method' => 'cod',
            'items'          => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated();

        $order = Order::where('number', $res->json('number'))->firstOrFail();

        $this->assertSame('4250.00', $order->items->first()->unit_price);
        // 8 500 d'articles + 1 500 de livraison Cocody : le chiffre d'affaires
        // se lit sur `orders.total`, c'est donc lui qui portait l'erreur.
        $this->assertSame('8500.00', $order->subtotal);
        $this->assertSame('10000.00', $order->total);
    }

    // ── Chemin 3 : caisse ──────────────────────────────────────────────────

    public function test_la_caisse_facture_le_prix_remise(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $product = $this->articleEnPromotion();

        Sanctum::actingAs($admin);

        $res = $this->postJson('/api/admin/pos/orders', [
            'customer'       => ['name' => 'Client Comptoir', 'phone' => '0700000001'],
            'items'          => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ])->assertCreated();

        $order = Order::where('number', $res->json('number'))->firstOrFail();

        $this->assertSame('4250.00', $order->items->first()->unit_price);
        $this->assertSame('8500.00', $order->subtotal);
    }

    // ── Une variante tarifée garde son prix ────────────────────────────────

    public function test_la_promotion_ne_deborde_pas_sur_le_prix_dune_variante(): void
    {
        $product = $this->articleEnPromotion();
        $variant = $product->variants()->create([
            'sku'       => 'VAR-200',
            'label'     => '200 ml',
            'price'     => 9000,
            'stock'     => 10,
            'is_active' => true,
        ]);

        $customer = User::factory()->create(['phone' => '0102030405']);
        $cart = Cart::create(['user_id' => $customer->id]);
        CartItem::create([
            'cart_id'    => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity'   => 1,
        ]);

        $this->assertSame(9000.0, $cart->fresh(['items.product', 'items.variant'])->subtotal);
    }
}
