<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\StockMovement;
use Modules\Delivery\Models\DeliveryZone;
use Modules\Order\Models\Order;
use Tests\TestCase;

/**
 * Remise à zéro avant la mise en service.
 *
 * La règle vérifiée ici tient en une phrase : **les transactions partent, la
 * configuration reste.** Une purge qui emporterait le catalogue ou les zones
 * de livraison obligerait à tout ressaisir la veille de l'ouverture ; une
 * purge qui laisserait les commandes de test ferait ouvrir la boutique sur un
 * chiffre d'affaires imaginaire.
 */
class ResetLaunchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
    }

    public function test_les_transactions_partent(): void
    {
        Order::factory()->count(3)->create();

        $this->artisan('shop:reset-launch --force')->assertSuccessful();

        $this->assertSame(0, Order::count());
        $this->assertSame(0, DB::table('order_items')->count());
    }

    /** Une purge qui emporte le catalogue oblige à tout ressaisir la veille. */
    public function test_la_configuration_reste(): void
    {
        $produit = Product::factory()->create(['stock' => 12]);
        $zone    = DeliveryZone::create([
            'group' => 'Grand Abidjan', 'name' => 'Cocody', 'country' => 'CI',
            'price' => 1500, 'active' => true,
        ]);
        $agent = User::factory()->create();
        $agent->syncRoles(['staff']);

        Order::factory()->count(2)->create();

        $this->artisan('shop:reset-launch --force')->assertSuccessful();

        $this->assertDatabaseHas('products',        ['id' => $produit->id]);
        $this->assertDatabaseHas('delivery_zones',  ['id' => $zone->id]);
        $this->assertDatabaseHas('users',           ['id' => $agent->id]);
    }

    /** Le registre ne se vide pas : il se rouvre sur une ligne par article. */
    public function test_le_registre_des_stocks_se_rouvre(): void
    {
        $produit = Product::factory()->create(['stock' => 12, 'track_stock' => true]);
        Order::factory()->create();

        $this->artisan('shop:reset-launch --force')->assertSuccessful();

        $ouverture = StockMovement::where('product_id', $produit->id)->get();

        $this->assertCount(1, $ouverture);
        $this->assertSame(StockMovement::TYPE_IMPORT, $ouverture->first()->type);
        $this->assertSame(12, $ouverture->first()->stock_after);
        $this->assertSame(12, $produit->fresh()->stock);
    }

    public function test_keep_stock_laisse_le_registre_intact(): void
    {
        $produit = Product::factory()->create(['stock' => 12, 'track_stock' => true]);

        StockMovement::create([
            'product_id' => $produit->id, 'type' => StockMovement::TYPE_SALE,
            'quantity' => -1, 'stock_before' => 13, 'stock_after' => 12,
        ]);

        $this->artisan('shop:reset-launch --force --keep-stock')->assertSuccessful();

        $this->assertSame(1, StockMovement::count());
        $this->assertSame(StockMovement::TYPE_SALE, StockMovement::first()->type);
    }

    /**
     * Les comptes de test se reconnaissent à leur domaine, et à rien d'autre :
     * une cliente réelle importée ne doit jamais tomber dans la purge.
     */
    public function test_les_comptes_de_test_ne_partent_quavec_loption(): void
    {
        $test    = User::factory()->create(['email' => 'awa@boutique.test']);
        $rapide  = User::factory()->create(['email' => 'x7f2@clients.local']);
        $cliente = User::factory()->create(['email' => 'fatou@gmail.com']);

        $this->artisan('shop:reset-launch --force')->assertSuccessful();
        $this->assertDatabaseHas('users', ['id' => $test->id]);

        $this->artisan('shop:reset-launch --force --clients')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $test->id]);
        $this->assertDatabaseMissing('users', ['id' => $rapide->id]);
        $this->assertDatabaseHas('users',     ['id' => $cliente->id]);
    }

    public function test_la_simulation_ne_touche_a_rien(): void
    {
        Order::factory()->count(2)->create();

        $this->artisan('shop:reset-launch --dry-run')->assertSuccessful();

        $this->assertSame(2, Order::count());
    }

    /** Le compteur de numéro repart à 1 : il se calcule sur les commandes de l'année. */
    public function test_la_numerotation_repart_a_un(): void
    {
        Order::factory()->count(4)->create();

        $this->artisan('shop:reset-launch --force')->assertSuccessful();

        $this->assertSame('ORD-' . now()->year . '-00001', Order::generateNumber());
    }

    public function test_zero_stock_prepare_un_comptage_complet(): void
    {
        $produit = Product::factory()->create(['stock' => 12, 'track_stock' => true]);

        $this->artisan('shop:reset-launch --force --zero-stock')->assertSuccessful();

        $this->assertSame(0, $produit->fresh()->stock);
    }
}
