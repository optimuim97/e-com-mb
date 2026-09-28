<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\StockMovement;
use Modules\Catalog\Services\StockLedger;
use Modules\Order\Models\Order;
use Tests\Concerns\ActingAsUser;
use Tests\TestCase;

/**
 * Registre des entrées et sorties de stock.
 *
 * Le compteur seul ne disait pas ce qui l'avait fait bouger : un écart entre
 * le comptage et l'écran restait inexplicable. Chaque déplacement laisse
 * désormais une ligne, et le compteur ne bouge plus sans elle.
 */
class StockMovementTest extends TestCase
{
    use RefreshDatabase, ActingAsUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Delivery\Database\Seeders\DeliveryZoneSeeder::class);
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->setUpUser();
    }

    private function adresse(): array
    {
        return [
            'first_name'    => 'Fatou',
            'last_name'     => 'Koné',
            'address_line1' => 'Cocody, Abidjan',
            'city'          => 'Abidjan',
            'commune'       => 'Cocody',
            'country'       => 'CI',
            'phone'         => '0102030405',
        ];
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        return $admin;
    }

    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->syncRoles(['staff']);

        return $agent;
    }

    // ── Le registre suit les ventes ────────────────────────────────────────

    public function test_une_commande_laisse_une_sortie_de_stock(): void
    {
        $produit = Product::factory()->create(['stock' => 10, 'price' => 5000]);
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $produit->id, 'quantity' => 3]);

        $numero = $this->asUser()
            ->postJson('/api/orders', ['shipping_address' => $this->adresse(), 'payment_method' => 'cod'])
            ->assertCreated()
            ->json('number');

        $commande  = Order::where('number', $numero)->firstOrFail();
        $mouvement = StockMovement::where('product_id', $produit->id)->sole();

        $this->assertSame(StockMovement::TYPE_SALE, $mouvement->type);
        $this->assertSame(-3, $mouvement->quantity);
        $this->assertSame(10, $mouvement->stock_before);
        $this->assertSame(7, $mouvement->stock_after);
        $this->assertSame($commande->id, $mouvement->reference_id);
        $this->assertSame(Order::class, $mouvement->reference_type);
        $this->assertSame(7, (int) $produit->fresh()->stock);
    }

    public function test_un_produit_sans_suivi_de_stock_ne_produit_aucun_mouvement(): void
    {
        $produit = Product::factory()->create(['stock' => 10, 'price' => 5000, 'track_stock' => false]);
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $produit->id, 'quantity' => 3]);

        $this->asUser()
            ->postJson('/api/orders', ['shipping_address' => $this->adresse(), 'payment_method' => 'cod'])
            ->assertCreated();

        $this->assertSame(0, StockMovement::count());
        $this->assertSame(10, (int) $produit->fresh()->stock);
    }

    // ── Garde-fou du registre ──────────────────────────────────────────────

    public function test_le_registre_refuse_de_passer_sous_zero(): void
    {
        $produit = Product::factory()->create(['stock' => 2]);
        $ledger  = app(StockLedger::class);

        $this->expectException(\RuntimeException::class);
        $ledger->out($produit, null, 5, StockMovement::TYPE_SALE);
    }

    public function test_aucune_ligne_nest_ecrite_quand_le_mouvement_est_refuse(): void
    {
        $produit = Product::factory()->create(['stock' => 2]);

        try {
            app(StockLedger::class)->out($produit, null, 5, StockMovement::TYPE_SALE);
        } catch (\RuntimeException) {
            // attendu
        }

        $this->assertSame(0, StockMovement::count());
        $this->assertSame(2, (int) $produit->fresh()->stock);
    }

    // ── Correction après comptage ──────────────────────────────────────────

    public function test_la_correction_enregistre_lecart(): void
    {
        $produit = Product::factory()->create(['stock' => 10]);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/stock-movements/adjust', [
            'product_id' => $produit->id,
            'counted'    => 7,
            'reason'     => 'Comptage du samedi',
        ])->assertCreated();

        $mouvement = StockMovement::sole();

        $this->assertSame(StockMovement::TYPE_ADJUSTMENT, $mouvement->type);
        $this->assertSame(-3, $mouvement->quantity);
        $this->assertSame(7, $mouvement->stock_after);
        $this->assertSame('Comptage du samedi', $mouvement->reason);
        $this->assertSame(7, (int) $produit->fresh()->stock);
    }

    public function test_la_correction_retient_son_auteur(): void
    {
        $produit = Product::factory()->create(['stock' => 10]);
        $admin   = $this->admin();

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/stock-movements/adjust', [
            'product_id' => $produit->id,
            'counted'    => 12,
            'reason'     => 'Retour fournisseur',
        ])->assertCreated();

        $this->assertSame($admin->id, StockMovement::sole()->user_id);
    }

    public function test_un_stock_deja_juste_nenregistre_rien(): void
    {
        $produit = Product::factory()->create(['stock' => 10]);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/stock-movements/adjust', [
            'product_id' => $produit->id,
            'counted'    => 10,
            'reason'     => 'Comptage conforme',
        ])->assertOk();

        $this->assertSame(0, StockMovement::count());
    }

    public function test_lagent_ne_peut_pas_corriger_un_stock(): void
    {
        $produit = Product::factory()->create(['stock' => 10]);

        Sanctum::actingAs($this->agent());

        $this->postJson('/api/admin/stock-movements/adjust', [
            'product_id' => $produit->id,
            'counted'    => 3,
            'reason'     => 'Manquant constaté',
        ])->assertForbidden();

        $this->assertSame(10, (int) $produit->fresh()->stock);
    }

    // ── Consultation ───────────────────────────────────────────────────────

    public function test_lagent_peut_consulter_lhistorique(): void
    {
        $produit = Product::factory()->create(['stock' => 10]);
        app(StockLedger::class)->out($produit, null, 2, StockMovement::TYPE_SALE, ['reason' => 'Vente comptoir']);

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/admin/stock-movements')
            ->assertOk()
            ->assertJsonPath('data.0.quantity', -2)
            ->assertJsonPath('data.0.direction', 'out')
            ->assertJsonPath('data.0.type_label', 'Vente')
            ->assertJsonPath('data.0.product.name', $produit->name);
    }

    public function test_le_cout_dentree_reste_prive_pour_lagent(): void
    {
        $produit = Product::factory()->create(['stock' => 0]);
        app(StockLedger::class)->in($produit, null, 5, StockMovement::TYPE_PURCHASE, ['unit_cost' => 1200]);

        Sanctum::actingAs($this->agent());
        $this->getJson('/api/admin/stock-movements')
            ->assertOk()
            ->assertJsonPath('data.0.unit_cost', null);

        Sanctum::actingAs($this->admin());
        $this->getJson('/api/admin/stock-movements')
            ->assertOk()
            ->assertJsonPath('data.0.unit_cost', '1200.00');
    }

    public function test_lhistorique_se_filtre_par_produit_et_par_sens(): void
    {
        $creme = Product::factory()->create(['stock' => 10]);
        $huile = Product::factory()->create(['stock' => 10]);
        $ledger = app(StockLedger::class);

        $ledger->out($creme, null, 2, StockMovement::TYPE_SALE);
        $ledger->in($creme,  null, 5, StockMovement::TYPE_PURCHASE);
        $ledger->out($huile, null, 1, StockMovement::TYPE_SALE);

        Sanctum::actingAs($this->admin());

        $this->getJson("/api/admin/stock-movements?product_id={$creme->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/admin/stock-movements?product_id={$creme->id}&direction=in")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.quantity', 5);
    }

    public function test_le_recapitulatif_totalise_la_periode(): void
    {
        $produit = Product::factory()->create(['stock' => 10]);
        $ledger  = app(StockLedger::class);

        $ledger->in($produit,  null, 20, StockMovement::TYPE_PURCHASE);
        $ledger->out($produit, null, 6,  StockMovement::TYPE_SALE);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/stock-movements/summary')
            ->assertOk()
            ->assertJsonPath('entrees', 20)
            ->assertJsonPath('sorties', 6)
            ->assertJsonPath('net', 14)
            ->assertJsonPath('mouvements', 2);
    }
}
