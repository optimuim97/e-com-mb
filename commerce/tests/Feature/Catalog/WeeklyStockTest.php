<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\StockMovement;
use Modules\Catalog\Models\WeeklyStockAllocation;
use Modules\Catalog\Services\StockLedger;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Tests\TestCase;

/**
 * Stock mis en vente, semaine par semaine.
 *
 * La boutique décide chaque lundi ce qu'elle accepte de vendre en ligne et
 * garde le reste au comptoir. Une seule quantité est stockée — l'allocation ;
 * le vendu et l'entré se calculent, pour qu'aucun second compteur ne puisse
 * diverger du réel.
 */
class WeeklyStockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(\Modules\Delivery\Database\Seeders\DeliveryZoneSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->syncRoles(['admin']);
    }

    private function lundi(): string
    {
        return now()->startOfWeek()->toDateString();
    }

    private function allouer(Product $product, ?int $quantite, ?string $note = null): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/weekly-stock', [
            'week' => $this->lundi(),
            'rows' => [['product_id' => $product->id, 'quantity' => $quantite, 'note' => $note]],
        ])->assertOk();
    }

    private function ligne(array $reponse, int $productId): array
    {
        return collect($reponse['rows'])->firstWhere('product_id', $productId);
    }

    private function tableau(): array
    {
        Sanctum::actingAs($this->admin);

        return $this->getJson('/api/admin/weekly-stock')->assertOk()->json();
    }

    private function vendre(Product $product, int $qte, string $status = 'processing'): Order
    {
        $order = Order::factory()->create(['status' => $status, 'user_id' => null]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'product_name' => $product->name, 'quantity' => $qte,
            'unit_price' => 5000, 'total_price' => 5000 * $qte,
        ]);

        return $order;
    }

    // ── Le tableau ─────────────────────────────────────────────────────────

    public function test_sans_allocation_larticle_nest_pas_plafonne(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock' => 12]);

        $ligne = $this->ligne($this->tableau(), $product->id);

        // Un lundi oublié ne doit pas fermer la boutique : le stock seul borne.
        $this->assertNull($ligne['allocated']);
        $this->assertNull($ligne['remaining']);
        $this->assertSame(12, $ligne['sellable']);
    }

    public function test_le_vendu_se_calcule_depuis_les_commandes(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock' => 20]);
        $this->allouer($product, 10);

        $this->vendre($product, 3);
        $this->vendre($product, 2);

        $ligne = $this->ligne($this->tableau(), $product->id);

        $this->assertSame(5, $ligne['sold']);
        $this->assertSame(5, $ligne['remaining']);
    }

    public function test_une_commande_annulee_ne_consomme_pas_lallocation(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock' => 20]);
        $this->allouer($product, 10);

        $this->vendre($product, 4);
        $this->vendre($product, 6, 'cancelled');

        // Compter l'annulée bloquerait la vente d'articles disponibles.
        $this->assertSame(4, $this->ligne($this->tableau(), $product->id)['sold']);
    }

    public function test_lentre_se_lit_dans_le_registre_des_mouvements(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock' => 5]);
        app(StockLedger::class)->in($product, null, 7, StockMovement::TYPE_PURCHASE);

        $this->assertSame(7, $this->ligne($this->tableau(), $product->id)['received']);
    }

    public function test_le_plafond_applique_est_le_plus_petit_des_deux(): void
    {
        // Allocation généreuse, stock modeste : on ne vend pas ce qu'on n'a pas.
        $product = Product::factory()->create(['is_active' => true, 'stock' => 3]);
        $this->allouer($product, 50);

        $this->assertSame(3, $this->ligne($this->tableau(), $product->id)['sellable']);
    }

    public function test_la_note_danomalie_est_conservee(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock' => 5]);
        $this->allouer($product, 4, 'Deux pots ébréchés');

        $this->assertSame('Deux pots ébréchés', $this->ligne($this->tableau(), $product->id)['note']);
    }

    public function test_retirer_la_quantite_supprime_le_plafond(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock' => 8]);
        $this->allouer($product, 4);
        $this->assertSame(4, $this->ligne($this->tableau(), $product->id)['allocated']);

        $this->allouer($product, null);

        // Absence de ligne, pas une allocation à zéro : les deux ne veulent pas
        // dire la même chose.
        $this->assertNull($this->ligne($this->tableau(), $product->id)['allocated']);
        $this->assertSame(0, WeeklyStockAllocation::count());
    }

    public function test_chaque_semaine_a_sa_propre_allocation(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock' => 30]);
        $this->allouer($product, 6);

        Sanctum::actingAs($this->admin);
        $suivante = now()->startOfWeek()->addWeek()->toDateString();

        $tableau = $this->getJson("/api/admin/weekly-stock?week={$suivante}")->assertOk()->json();

        $this->assertSame($suivante, $tableau['week_start']);
        $this->assertNull($this->ligne($tableau, $product->id)['allocated']);
    }

    // ── Habilitations ──────────────────────────────────────────────────────

    public function test_la_saisie_exige_lhabilitation(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $agent   = User::factory()->create();
        $agent->syncRoles(['staff']);

        Sanctum::actingAs($agent);

        // Décider de ce qui part à la vente, c'est un arbitrage : le personnel
        // consulte, il ne tranche pas.
        $this->getJson('/api/admin/weekly-stock')->assertOk();
        $this->postJson('/api/admin/weekly-stock', [
            'week' => $this->lundi(),
            'rows' => [['product_id' => $product->id, 'quantity' => 3]],
        ])->assertForbidden();
    }

    // ── Le plafond à la vente ──────────────────────────────────────────────

    private function commanderEnLigne(Product $product, int $qte)
    {
        return $this->postJson('/api/quick-order', [
            'name' => 'Awa Konaté', 'phone' => '0102030405', 'commune' => 'Cocody',
            'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'quantity' => $qte]],
        ]);
    }

    public function test_la_vente_en_ligne_respecte_lallocation(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock' => 50, 'track_stock' => true]);
        $this->allouer($product, 5);

        $this->commanderEnLigne($product, 5)->assertCreated();

        // L'allocation est consommée : la suivante doit être refusée bien que
        // le stock physique le permette largement.
        $this->commanderEnLigne($product, 1)
            ->assertStatus(422)
            ->assertJsonFragment(['message' => "« {$product->name} » n'est plus disponible à la vente en ligne cette semaine."]);
    }

    public function test_le_message_distingue_le_quota_de_la_rupture(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock' => 50, 'track_stock' => true]);
        $this->allouer($product, 2);

        $reponse = $this->commanderEnLigne($product, 5)->assertStatus(422);

        // « Plus de stock » et « quota atteint » n'appellent pas la même action
        // de la boutique : le message ne doit pas les confondre.
        $this->assertStringContainsString('à la vente en ligne cette semaine', $reponse->json('message'));
        $this->assertStringNotContainsString('Stock insuffisant', $reponse->json('message'));
    }

    public function test_sans_allocation_la_vente_reste_libre(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock' => 50, 'track_stock' => true]);

        $this->commanderEnLigne($product, 9)->assertCreated();
    }

    public function test_le_comptoir_nest_pas_soumis_au_quota_du_site(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock' => 50, 'track_stock' => true]);
        $this->allouer($product, 1);

        Sanctum::actingAs($this->admin);

        // La vendeuse puise dans la réserve : lui appliquer le quota du site
        // lui interdirait de vendre un article qu'elle a sous la main.
        $this->postJson('/api/admin/pos/orders', [
            'customer' => ['name' => 'Client Comptoir', 'phone' => '0700000009'],
            'items'    => [['product_id' => $product->id, 'quantity' => 8]],
            'payment_status' => 'paid', 'payment_method' => 'cash',
        ])->assertCreated();
    }

    public function test_une_allocation_a_zero_interdit_la_vente_en_ligne(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock' => 50, 'track_stock' => true]);
        $this->allouer($product, 0);

        $this->commanderEnLigne($product, 1)->assertStatus(422);
    }
}
