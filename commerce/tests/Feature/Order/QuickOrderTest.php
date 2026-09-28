<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Models\Setting;
use Modules\Catalog\Models\Product;
use Modules\Order\Models\Order;
use Tests\TestCase;

/**
 * Commande rapide (invité, sans authentification) — POST /api/quick-order.
 * Crée/retrouve l'utilisateur par téléphone, crée la commande, renvoie un token.
 */
class QuickOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Sans zones, toute adresse passe pour hors zone et le
        // règlement est refusé : on reproduit la configuration réelle.
        $this->seed(\Modules\Delivery\Database\Seeders\DeliveryZoneSeeder::class);
    }

    private function payload(array $overrides = []): array
    {
        $product = Product::factory()->priced(10000)->create();

        return array_merge([
            'name'           => 'Fatou Konaté',
            'phone'          => '0102030405',
            'commune'        => 'Cocody',
            'payment_method' => 'cod',
            'items'          => [['product_id' => $product->id, 'quantity' => 2]],
        ], $overrides);
    }

    public function test_invite_peut_passer_une_commande_rapide(): void
    {
        $res = $this->postJson('/api/quick-order', $this->payload());

        $res->assertCreated()
            ->assertJsonStructure(['number', 'status', 'total', 'access_token', 'token_type']);

        $this->assertDatabaseHas('orders', ['number' => $res->json('number'), 'status' => 'pending']);
        $this->assertDatabaseHas('users', ['phone' => '0102030405']);
        $this->assertSame('21500.00', $res->json('total'));   // 20 000 + 1 500 de livraison Cocody
    }

    public function test_meme_telephone_reutilise_le_meme_utilisateur(): void
    {
        $this->postJson('/api/quick-order', $this->payload())->assertCreated();
        $this->postJson('/api/quick-order', $this->payload())->assertCreated();

        $this->assertSame(1, User::where('phone', '0102030405')->count());
    }

    public function test_cod_est_mappe_sur_delivery(): void
    {
        $res = $this->postJson('/api/quick-order', $this->payload(['payment_method' => 'cod']));
        $res->assertCreated();

        $this->assertSame('delivery', Order::where('number', $res->json('number'))->value('payment_method'));
    }

    public function test_items_manquants_retourne_422(): void
    {
        $this->postJson('/api/quick-order', $this->payload(['items' => []]))
             ->assertUnprocessable()
             ->assertJsonValidationErrors('items');
    }

    public function test_methode_paiement_invalide_retourne_422(): void
    {
        // 'card' n'est pas accepté par la commande rapide.
        $this->postJson('/api/quick-order', $this->payload(['payment_method' => 'card']))
             ->assertUnprocessable()
             ->assertJsonValidationErrors('payment_method');
    }

    public function test_stock_decremente(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 5000]);

        $this->postJson('/api/quick-order', $this->payload([
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ]))->assertCreated();

        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_commande_rapide_desactivee_retourne_403(): void
    {
        Setting::set('enable_quick_order', false);

        $this->postJson('/api/quick-order', $this->payload())
             ->assertForbidden();
    }
}
