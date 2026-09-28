<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Order\Models\Order;
use Tests\TestCase;

/**
 * Gestion admin des commandes — statuts, paiement, suivi, masquage finance.
 */
class AdminOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->syncRoles(['admin']);

        $this->staff = User::factory()->create();
        $this->staff->syncRoles(['staff']); // orders.view mais PAS finance.view
    }

    public function test_admin_met_a_jour_le_statut(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);

        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/orders/{$order->id}", ['status' => 'confirmed'])
             ->assertOk();

        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_statut_invalide_rejete(): void
    {
        $order = Order::factory()->create();

        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/orders/{$order->id}", ['status' => 'en_route_vers_mars'])
             ->assertUnprocessable()
             ->assertJsonValidationErrors('status');
    }

    public function test_marquer_paye(): void
    {
        $order = Order::factory()->create(['paid_at' => null]);

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/orders/{$order->id}/mark-paid")->assertOk();

        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_generer_numero_de_suivi(): void
    {
        $order = Order::factory()->create();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/orders/{$order->id}/generate-tracking")
             ->assertOk()
             ->assertJsonStructure(['tracking_number']);
    }

    public function test_frais_livraison_recalcule_le_total(): void
    {
        $order = Order::factory()->create([
            'subtotal' => 10000, 'discount_amount' => 0, 'shipping_cost' => 0, 'total' => 10000,
        ]);

        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/orders/{$order->id}", ['shipping_cost' => 1500])
             ->assertOk();

        $this->assertSame('11500.00', (string) $order->fresh()->total);
    }

    public function test_staff_sans_finance_ne_voit_pas_les_montants(): void
    {
        $order = Order::factory()->create(['total' => 25000, 'subtotal' => 25000]);

        Sanctum::actingAs($this->staff);
        $this->getJson("/api/admin/orders/{$order->id}")
             ->assertOk()
             ->assertJsonPath('data.total', null)
             ->assertJsonPath('data.subtotal', null)
             ->assertJsonPath('data.amounts_hidden', true);
    }

    public function test_admin_voit_les_montants(): void
    {
        $order = Order::factory()->create(['total' => 25000, 'subtotal' => 25000]);

        Sanctum::actingAs($this->admin);
        $this->getJson("/api/admin/orders/{$order->id}")
             ->assertOk()
             ->assertJsonPath('data.amounts_hidden', false)
             ->assertJsonPath('data.total', '25000.00');
    }
}
