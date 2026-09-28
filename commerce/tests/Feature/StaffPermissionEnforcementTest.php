<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Catalog\Models\Product;
use Tests\TestCase;

/**
 * Vérifie que l'agent staff (rôle `staff`, ex : Nathalie) est bien
 * restreint : pas d'accès administration, finance masquée ; l'admin garde tout.
 */
class StaffPermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);        // rôles de base + customer
        $this->seed(PermissionsSeeder::class);  // permissions + admin/manager/staff

        $this->staff = User::factory()->create(['email' => 'agent.test@boutique.local']);
        $this->staff->syncRoles(['staff']);

        $this->admin = User::factory()->create(['email' => 'admin.test@boutique.local']);
        $this->admin->syncRoles(['admin']);
    }

    private function makeProduct(): Product
    {
        return Product::create([
            'name'  => 'Produit test',
            'slug'  => 'produit-test-'.uniqid(),
            'price' => 5000,
        ]);
    }

    public function test_staff_role_excludes_finance_and_admin_permissions(): void
    {
        $this->assertTrue($this->staff->can('orders.view'));
        $this->assertFalse($this->staff->can('finance.view'));
        $this->assertFalse($this->staff->can('settings.view'));
        $this->assertFalse($this->staff->can('users.view'));
        $this->assertFalse($this->staff->can('products.delete'));
    }

    public function test_dashboard_hides_finance_for_staff_but_not_for_admin(): void
    {
        Sanctum::actingAs($this->staff);
        $staffRes = $this->getJson('/api/admin/dashboard');
        $staffRes->assertOk();
        $staffRes->assertJsonMissingPath('stats.revenue');
        $staffRes->assertJsonMissingPath('stats.today_revenue');
        $staffRes->assertJsonPath('can_finance', false);
        $this->assertSame([], $staffRes->json('sales_chart'));

        Sanctum::actingAs($this->admin);
        $adminRes = $this->getJson('/api/admin/dashboard');
        $adminRes->assertOk();
        $adminRes->assertJsonPath('can_finance', true);
        $this->assertArrayHasKey('revenue', $adminRes->json('stats'));
    }

    public function test_staff_is_denied_admin_only_endpoints(): void
    {
        Sanctum::actingAs($this->staff);

        // Pas de settings.view
        $this->getJson('/api/admin/settings')->assertForbidden();
        // Pas de roles.view (gestion des accès réservée à l'admin)
        $this->getJson('/api/admin/roles')->assertForbidden();
        // Pas de permissions.view
        $this->getJson('/api/admin/permissions')->assertForbidden();
        // Pas de products.delete : sur une ressource réelle, le gate refuse (403)
        $product = $this->makeProduct();
        $this->deleteJson("/api/admin/products/{$product->id}")->assertForbidden();
    }

    public function test_staff_can_reach_permitted_endpoints(): void
    {
        Sanctum::actingAs($this->staff);

        // A products.view
        $this->getJson('/api/admin/products')->assertOk();
        // A orders.view
        $this->getJson('/api/admin/orders')->assertOk();
        // A customers.view → liste clients (service client)
        $this->getJson('/api/admin/users')->assertOk();
    }

    public function test_admin_passes_permission_gated_endpoints(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/admin/settings')->assertOk();
        $this->getJson('/api/admin/roles')->assertOk();
        $this->getJson('/api/admin/permissions')->assertOk();
        // products.delete accordé → l'admin peut supprimer un vrai produit
        $product = $this->makeProduct();
        $this->deleteJson("/api/admin/products/{$product->id}")->assertOk();
    }
}
