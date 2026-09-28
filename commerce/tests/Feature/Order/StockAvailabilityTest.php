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
use Tests\Concerns\ActingAsUser;
use Tests\TestCase;

/**
 * Disponibilité du stock sur TOUS les points d'entrée de commande.
 * Une commande dont la quantité dépasse le stock d'un produit suivi doit être
 * REFUSÉE (422) et le stock ne doit pas devenir négatif.
 */
class StockAvailabilityTest extends TestCase
{
    use RefreshDatabase, ActingAsUser;

    protected function setUp(): void
    {
        parent::setUp();
        // Les zones de livraison conditionnent le moyen de paiement :
        // sans elles, toute adresse est vue « hors zone » et le checkout
        // refuse le règlement. On reproduit donc la configuration réelle.
        $this->seed(\Modules\Delivery\Database\Seeders\DeliveryZoneSeeder::class);
        $this->setUpUser();
    }

    public function test_checkout_boutique_panier_db_refuse_survente(): void
    {
        $product = Product::factory()->create(['stock' => 2, 'price' => 5000]);
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 5]);

        $this->asUser()->postJson('/api/orders', [
            'shipping_address' => [
                'first_name' => 'Fatou', 'last_name' => 'Konaté', 'address_line1' => 'Cocody',
                'city' => 'Abidjan',
            'commune'       => 'Cocody', 'country' => 'CI', 'phone' => '0102030405',
            ],
            'payment_method' => 'cod',
        ])->assertStatus(422)->assertJsonFragment(['message' => "Stock insuffisant pour « {$product->name} » (disponible : 2, demandé : 5)."]);

        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_checkout_boutique_payload_refuse_survente(): void
    {
        $product = Product::factory()->create(['stock' => 2, 'price' => 5000]);

        $this->asUser()->postJson('/api/orders', [
            'shipping_address' => [
                'first_name' => 'Fatou', 'last_name' => 'Konaté', 'address_line1' => 'Cocody',
                'city' => 'Abidjan',
            'commune'       => 'Cocody', 'country' => 'CI', 'phone' => '0102030405',
            ],
            'payment_method' => 'cod',
            'items'          => [['product_id' => $product->id, 'quantity' => 5]],
        ])->assertStatus(422);

        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_commande_rapide_refuse_survente(): void
    {
        $product = Product::factory()->create(['stock' => 2, 'price' => 5000]);

        $this->postJson('/api/quick-order', [
            'name' => 'Fatou Konaté', 'phone' => '0102030409', 'commune' => 'Cocody',
            'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ])->assertStatus(422);

        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_pos_refuse_survente(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $product = Product::factory()->create(['stock' => 2, 'price' => 5000]);

        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/pos/orders', [
            'customer'       => ['name' => 'Comptoir'],
            'items'          => [['product_id' => $product->id, 'quantity' => 5]],
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ])->assertStatus(422);

        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_produit_sans_suivi_stock_autorise_toute_quantite(): void
    {
        // track_stock = false → pas de contrôle, vente illimitée assumée.
        $product = Product::factory()->create(['stock' => 0, 'price' => 5000, 'track_stock' => false]);

        $this->postJson('/api/quick-order', [
            'name' => 'Fatou Konaté', 'phone' => '0102030411', 'commune' => 'Cocody',
            'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'quantity' => 99]],
        ])->assertCreated();
    }
}
