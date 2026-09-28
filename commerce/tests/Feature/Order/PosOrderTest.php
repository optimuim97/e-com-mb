<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Catalog\Models\Product;
use Modules\Order\Models\Order;
use Tests\TestCase;

/**
 * Commande POS admin (caisse / vente manuelle) — POST /api/admin/pos/orders.
 * L'admin maîtrise prix, remise, livraison et statut de paiement.
 */
class PosOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->syncRoles(['admin']);
    }

    private function payload(array $overrides = [], ?Product $product = null): array
    {
        $product ??= Product::factory()->create(['stock' => 20, 'price' => 5000]);

        return array_merge([
            'customer'       => ['name' => 'Client Comptoir', 'phone' => '0700000001'],
            'items'          => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ], $overrides);
    }

    public function test_pos_exige_authentification(): void
    {
        $this->postJson('/api/admin/pos/orders', $this->payload())->assertUnauthorized();
    }

    public function test_client_sans_permission_est_interdit(): void
    {
        $customer = User::factory()->create();
        $customer->syncRoles(['customer']);

        Sanctum::actingAs($customer);
        $this->postJson('/api/admin/pos/orders', $this->payload())->assertForbidden();
    }

    public function test_admin_cree_commande_pos_payee(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->postJson('/api/admin/pos/orders', $this->payload());
        $res->assertCreated();

        $order = Order::where('number', $res->json('number'))->first();
        $this->assertNotNull($order);
        $this->assertSame('confirmed', $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('pos', $order->shipping_method);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'completed']);
    }

    public function test_commande_pos_impayee_reste_pending(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->postJson('/api/admin/pos/orders', $this->payload(['payment_status' => 'unpaid']));
        $res->assertCreated();

        $order = Order::where('number', $res->json('number'))->first();
        $this->assertSame('pending', $order->status);
        $this->assertNull($order->paid_at);
    }

    public function test_stock_decremente(): void
    {
        Sanctum::actingAs($this->admin);
        $product = Product::factory()->create(['stock' => 20, 'price' => 5000]);

        $this->postJson('/api/admin/pos/orders', $this->payload([
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ], $product))->assertCreated();

        $this->assertSame(15, $product->fresh()->stock);
    }

    public function test_total_pos_applique_remise_et_frais(): void
    {
        Sanctum::actingAs($this->admin);
        $product = Product::factory()->create(['stock' => 20, 'price' => 5000]);

        // 2 × 5000 = 10000 - 1500 (remise) + 1000 (livraison) = 9500
        $res = $this->postJson('/api/admin/pos/orders', $this->payload([
            'items'           => [['product_id' => $product->id, 'quantity' => 2]],
            'discount_amount' => 1500,
            'shipping_cost'   => 1000,
        ], $product));

        $res->assertCreated();
        $this->assertSame('9500.00', (string) Order::where('number', $res->json('number'))->value('total'));
    }

    public function test_pos_refuse_la_survente(): void
    {
        Sanctum::actingAs($this->admin);
        $product = Product::factory()->create(['stock' => 2, 'price' => 5000]);

        $this->postJson('/api/admin/pos/orders', $this->payload([
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ], $product))->assertStatus(422);

        // Le stock ne bouge pas (contrôle avant écriture).
        $this->assertSame(2, $product->fresh()->stock);
    }
}
