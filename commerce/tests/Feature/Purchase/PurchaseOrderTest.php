<?php

namespace Tests\Feature\Purchase;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admin\Models\Setting;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\StockMovement;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\Supplier;
use Modules\Purchase\Support\PurchaseModule;
use Tests\TestCase;

/**
 * Module Achats : bons d'achat, prix de revient et coût moyen pondéré.
 *
 * Le module est éteint par défaut et réservé à l'administration : prix
 * fournisseurs et marges n'ont pas à s'afficher dans une boutique qui ne les
 * gère pas, ni à circuler dans l'équipe.
 */
class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
    }

    private function activerModule(): void
    {
        Setting::set(PurchaseModule::SETTING, 'true');
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->syncRoles(['admin']);

        return $u;
    }

    private function role(string $nom): User
    {
        $u = User::factory()->create();
        $u->syncRoles([$nom]);

        return $u;
    }

    /** @param array<int, array<string, mixed>> $lignes */
    private function creerBon(array $lignes, array $extra = []): array
    {
        $reponse = $this->postJson('/api/admin/purchase-orders', array_merge([
            'items' => $lignes,
        ], $extra))->assertCreated();

        return $reponse->json();
    }

    // ── Interrupteur du module ─────────────────────────────────────────────

    public function test_le_module_est_eteint_par_defaut(): void
    {
        $this->assertFalse(PurchaseModule::enabled());

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/purchase-orders')
            ->assertForbidden()
            ->assertJsonPath('message', "Le module Achats n'est pas activé pour cette boutique.");
    }

    public function test_une_fois_active_ladmin_y_accede(): void
    {
        $this->activerModule();
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/purchase-orders')->assertOk();
    }

    // ── Cloisonnement ──────────────────────────────────────────────────────

    public function test_lagent_na_pas_acces_au_module(): void
    {
        $this->activerModule();
        Sanctum::actingAs($this->role('staff'));

        $this->getJson('/api/admin/purchase-orders')->assertForbidden();
        $this->getJson('/api/admin/suppliers')->assertForbidden();
    }

    public function test_la_gerante_na_pas_acces_au_module(): void
    {
        $this->activerModule();
        Sanctum::actingAs($this->role('manager'));

        $this->getJson('/api/admin/purchase-orders')->assertForbidden();
    }

    // ── Saisie d'un bon ────────────────────────────────────────────────────

    public function test_les_totaux_sont_calcules_par_le_serveur(): void
    {
        $this->activerModule();
        Sanctum::actingAs($this->admin());

        $creme = Product::factory()->create(['stock' => 0]);
        $huile = Product::factory()->create(['stock' => 0]);

        $bon = $this->creerBon([
            ['product_id' => $creme->id, 'quantity' => 10, 'unit_cost' => 1000],
            ['product_id' => $huile->id, 'quantity' => 5,  'unit_cost' => 2000],
        ], ['shipping_cost' => 5000]);

        // 10 × 1 000 + 5 × 2 000 = 20 000, + 5 000 de transport = 25 000.
        $this->assertSame('20000.00', $bon['items_total']);
        $this->assertSame('25000.00', $bon['total']);
        $this->assertSame('draft', $bon['status']);
        $this->assertMatchesRegularExpression('/^ACH-\d{4}-\d{5}$/', $bon['number']);
    }

    public function test_la_saisie_ne_touche_pas_au_stock(): void
    {
        $this->activerModule();
        Sanctum::actingAs($this->admin());

        $produit = Product::factory()->create(['stock' => 4]);
        $this->creerBon([['product_id' => $produit->id, 'quantity' => 10, 'unit_cost' => 1000]]);

        $this->assertSame(4, (int) $produit->fresh()->stock);
        $this->assertSame(0, StockMovement::count());
    }

    // ── Réception ──────────────────────────────────────────────────────────

    public function test_la_reception_fait_entrer_le_stock(): void
    {
        $this->activerModule();
        Sanctum::actingAs($this->admin());

        $produit = Product::factory()->create(['stock' => 4]);
        $bon = $this->creerBon([['product_id' => $produit->id, 'quantity' => 10, 'unit_cost' => 1000]]);

        $this->postJson("/api/admin/purchase-orders/{$bon['id']}/receive")->assertOk();

        $this->assertSame(14, (int) $produit->fresh()->stock);
        $this->assertSame('received', PurchaseOrder::find($bon['id'])->status);
    }

    public function test_lentree_apparait_dans_le_registre_avec_son_cout(): void
    {
        $this->activerModule();
        Sanctum::actingAs($this->admin());

        $produit = Product::factory()->create(['stock' => 0]);
        $bon = $this->creerBon([['product_id' => $produit->id, 'quantity' => 10, 'unit_cost' => 1000]]);

        $this->postJson("/api/admin/purchase-orders/{$bon['id']}/receive")->assertOk();

        $mouvement = StockMovement::sole();

        $this->assertSame(StockMovement::TYPE_PURCHASE, $mouvement->type);
        $this->assertSame(10, $mouvement->quantity);
        $this->assertSame('1000.00', $mouvement->unit_cost);
        $this->assertSame(PurchaseOrder::class, $mouvement->reference_type);
        $this->assertStringContainsString($bon['number'], $mouvement->reason);
    }

    public function test_les_frais_annexes_se_repartissent_au_prorata_de_la_valeur(): void
    {
        $this->activerModule();
        Sanctum::actingAs($this->admin());

        $creme = Product::factory()->create(['stock' => 0]);
        $huile = Product::factory()->create(['stock' => 0]);

        // Lignes : 10 000 (crème) et 10 000 (huile) → 50/50 des frais.
        // 6 000 de transport → 3 000 par ligne.
        //   crème : 10 unités → 1 000 + 300 = 1 300
        //   huile :  5 unités → 2 000 + 600 = 2 600
        $bon = $this->creerBon([
            ['product_id' => $creme->id, 'quantity' => 10, 'unit_cost' => 1000],
            ['product_id' => $huile->id, 'quantity' => 5,  'unit_cost' => 2000],
        ], ['shipping_cost' => 6000]);

        $this->postJson("/api/admin/purchase-orders/{$bon['id']}/receive")->assertOk();

        $lignes = collect(
            $this->getJson("/api/admin/purchase-orders/{$bon['id']}")->assertOk()->json('data.items')
        )->keyBy('product_id');

        $this->assertSame('1300.00', $lignes[$creme->id]['landed_unit_cost']);
        $this->assertSame('2600.00', $lignes[$huile->id]['landed_unit_cost']);
    }

    // ── Coût moyen unitaire pondéré ────────────────────────────────────────

    public function test_le_cmup_part_du_cout_dentree_sur_un_stock_vide(): void
    {
        $this->activerModule();
        Sanctum::actingAs($this->admin());

        $produit = Product::factory()->create(['stock' => 0, 'cost_price' => null]);
        $bon = $this->creerBon([['product_id' => $produit->id, 'quantity' => 10, 'unit_cost' => 1000]]);

        $this->postJson("/api/admin/purchase-orders/{$bon['id']}/receive")->assertOk();

        $this->assertSame('1000.00', $produit->fresh()->average_cost);
    }

    public function test_le_cmup_se_pondere_a_la_deuxieme_entree(): void
    {
        $this->activerModule();
        Sanctum::actingAs($this->admin());

        $produit = Product::factory()->create(['stock' => 0, 'cost_price' => null]);

        // 10 à 1 000, puis 10 à 2 000 → (10×1000 + 10×2000) / 20 = 1 500.
        $premier = $this->creerBon([['product_id' => $produit->id, 'quantity' => 10, 'unit_cost' => 1000]]);
        $this->postJson("/api/admin/purchase-orders/{$premier['id']}/receive")->assertOk();

        $second = $this->creerBon([['product_id' => $produit->id, 'quantity' => 10, 'unit_cost' => 2000]]);
        $this->postJson("/api/admin/purchase-orders/{$second['id']}/receive")->assertOk();

        $produit->refresh();

        $this->assertSame(20, (int) $produit->stock);
        $this->assertSame('1500.00', $produit->average_cost);
        // Le coût de référence suit la dernière entrée, la moyenne les lisse.
        $this->assertSame('2000.00', $produit->cost_price);
    }

    public function test_le_cmup_tient_compte_du_stock_deja_present(): void
    {
        $this->activerModule();
        Sanctum::actingAs($this->admin());

        // 5 pots déjà en rayon, valorisés 1 000 ; on en reçoit 15 à 2 000.
        // (5×1000 + 15×2000) / 20 = 1 750.
        $produit = Product::factory()->create(['stock' => 5, 'cost_price' => 1000, 'average_cost' => 1000]);

        $bon = $this->creerBon([['product_id' => $produit->id, 'quantity' => 15, 'unit_cost' => 2000]]);
        $this->postJson("/api/admin/purchase-orders/{$bon['id']}/receive")->assertOk();

        $this->assertSame('1750.00', $produit->fresh()->average_cost);
    }

    // ── Un bon reçu est figé ───────────────────────────────────────────────

    public function test_on_ne_receptionne_pas_deux_fois(): void
    {
        $this->activerModule();
        Sanctum::actingAs($this->admin());

        $produit = Product::factory()->create(['stock' => 0]);
        $bon = $this->creerBon([['product_id' => $produit->id, 'quantity' => 10, 'unit_cost' => 1000]]);

        $this->postJson("/api/admin/purchase-orders/{$bon['id']}/receive")->assertOk();
        $this->postJson("/api/admin/purchase-orders/{$bon['id']}/receive")->assertStatus(422);

        $this->assertSame(10, (int) $produit->fresh()->stock);
        $this->assertSame(1, StockMovement::count());
    }

    public function test_un_bon_recu_nest_plus_modifiable(): void
    {
        $this->activerModule();
        Sanctum::actingAs($this->admin());

        $produit = Product::factory()->create(['stock' => 0]);
        $bon = $this->creerBon([['product_id' => $produit->id, 'quantity' => 10, 'unit_cost' => 1000]]);
        $this->postJson("/api/admin/purchase-orders/{$bon['id']}/receive")->assertOk();

        $this->putJson("/api/admin/purchase-orders/{$bon['id']}", [
            'items' => [['product_id' => $produit->id, 'quantity' => 99, 'unit_cost' => 1]],
        ])->assertStatus(422);

        $this->assertSame(10, (int) $produit->fresh()->stock);
    }

    public function test_un_bon_recu_nest_pas_supprimable(): void
    {
        $this->activerModule();
        Sanctum::actingAs($this->admin());

        $produit = Product::factory()->create(['stock' => 0]);
        $bon = $this->creerBon([['product_id' => $produit->id, 'quantity' => 10, 'unit_cost' => 1000]]);
        $this->postJson("/api/admin/purchase-orders/{$bon['id']}/receive")->assertOk();

        $this->deleteJson("/api/admin/purchase-orders/{$bon['id']}")->assertStatus(422);
        $this->assertNotNull(PurchaseOrder::find($bon['id']));
    }

    public function test_un_brouillon_se_modifie_et_se_supprime(): void
    {
        $this->activerModule();
        Sanctum::actingAs($this->admin());

        $produit = Product::factory()->create(['stock' => 0]);
        $bon = $this->creerBon([['product_id' => $produit->id, 'quantity' => 10, 'unit_cost' => 1000]]);

        $this->putJson("/api/admin/purchase-orders/{$bon['id']}", [
            'items' => [['product_id' => $produit->id, 'quantity' => 4, 'unit_cost' => 1500]],
        ])->assertOk()->assertJsonPath('items_total', '6000.00');

        $this->deleteJson("/api/admin/purchase-orders/{$bon['id']}")->assertOk();
    }

    // ── Fournisseurs ───────────────────────────────────────────────────────

    public function test_un_fournisseur_rattache_est_archive_et_non_supprime(): void
    {
        $this->activerModule();
        Sanctum::actingAs($this->admin());

        $fournisseur = Supplier::create(['name' => 'Cosmétiques du Plateau']);
        $produit     = Product::factory()->create(['stock' => 0]);

        $this->creerBon(
            [['product_id' => $produit->id, 'quantity' => 3, 'unit_cost' => 500]],
            ['supplier_id' => $fournisseur->id],
        );

        $this->deleteJson("/api/admin/suppliers/{$fournisseur->id}")
            ->assertOk()
            ->assertJsonPath('archived', true);

        $fournisseur->refresh();
        $this->assertFalse($fournisseur->is_active);
        $this->assertNull($fournisseur->deleted_at);
    }

    public function test_un_fournisseur_sans_bon_se_supprime(): void
    {
        $this->activerModule();
        Sanctum::actingAs($this->admin());

        $fournisseur = Supplier::create(['name' => 'Essai']);

        $this->deleteJson("/api/admin/suppliers/{$fournisseur->id}")->assertOk();
        $this->assertSoftDeleted('suppliers', ['id' => $fournisseur->id]);
    }
}
