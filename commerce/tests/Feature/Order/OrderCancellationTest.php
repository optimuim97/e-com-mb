<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admin\Models\Setting;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\StockMovement;
use Modules\Order\Models\Order;
use Modules\Order\Services\OrderCancellation;
use Tests\Concerns\ActingAsUser;
use Tests\TestCase;

/**
 * Annulation d'une commande par la cliente.
 *
 * Fenêtre de rétractation réglable (24 h par défaut), et surtout retour du
 * stock : les articles sont décomptés dès la création, une annulation qui ne
 * les rend pas retirerait de la vente des pots restés en rayon.
 */
class OrderCancellationTest extends TestCase
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

    /** Passe une commande de $qte articles et renvoie [produit, commande]. */
    private function commander(int $qte = 3, int $stock = 10): array
    {
        $produit = Product::factory()->create(['stock' => $stock, 'price' => 5000]);
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $produit->id, 'quantity' => $qte]);

        $numero = $this->asUser()
            ->postJson('/api/orders', ['shipping_address' => $this->adresse(), 'payment_method' => 'cod'])
            ->assertCreated()
            ->json('number');

        return [$produit, Order::where('number', $numero)->firstOrFail()];
    }

    // ── Annulation dans les temps ──────────────────────────────────────────

    public function test_la_cliente_peut_annuler_dans_le_delai(): void
    {
        [, $commande] = $this->commander();

        $this->asUser()
            ->postJson("/api/orders/{$commande->number}/cancel")
            ->assertOk()
            ->assertJsonPath('order.status', 'cancelled');

        $this->assertSame('cancelled', $commande->fresh()->status);
    }

    public function test_lannulation_rend_le_stock(): void
    {
        [$produit, $commande] = $this->commander(qte: 3, stock: 10);

        $this->assertSame(7, (int) $produit->fresh()->stock);

        $this->asUser()->postJson("/api/orders/{$commande->number}/cancel")->assertOk();

        $this->assertSame(10, (int) $produit->fresh()->stock);
    }

    public function test_le_retour_de_stock_est_trace(): void
    {
        [$produit, $commande] = $this->commander(qte: 3);

        $this->asUser()->postJson("/api/orders/{$commande->number}/cancel")->assertOk();

        $retour = StockMovement::where('type', StockMovement::TYPE_CANCELLATION)->sole();

        $this->assertSame(3, $retour->quantity);
        $this->assertSame($produit->id, $retour->product_id);
        $this->assertSame($commande->id, $retour->reference_id);
        $this->assertStringContainsString($commande->number, $retour->reason);
    }

    public function test_annuler_deux_fois_ne_credite_le_stock_quune_fois(): void
    {
        [$produit, $commande] = $this->commander(qte: 3, stock: 10);

        $this->asUser()->postJson("/api/orders/{$commande->number}/cancel")->assertOk();
        $this->asUser()->postJson("/api/orders/{$commande->number}/cancel")->assertStatus(422);

        $this->assertSame(10, (int) $produit->fresh()->stock);
        $this->assertSame(1, StockMovement::where('type', StockMovement::TYPE_CANCELLATION)->count());
    }

    // ── Fenêtre dépassée ───────────────────────────────────────────────────

    public function test_passe_le_delai_lannulation_est_refusee(): void
    {
        [$produit, $commande] = $this->commander(qte: 3, stock: 10);

        // 25 h plus tard : au-delà des 24 h par défaut.
        $commande->forceFill(['created_at' => now()->subHours(25)])->save();

        $this->asUser()
            ->postJson("/api/orders/{$commande->number}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('message', "Le délai d'annulation en ligne est dépassé : contactez-nous et nous nous en occupons.");

        $this->assertSame('pending', $commande->fresh()->status);
        $this->assertSame(7, (int) $produit->fresh()->stock);
    }

    public function test_le_delai_est_parametrable(): void
    {
        Setting::set(OrderCancellation::DELAY_SETTING, '48');

        [, $commande] = $this->commander();
        $commande->forceFill(['created_at' => now()->subHours(30)])->save();

        // 30 h : hors des 24 h par défaut, mais dans les 48 h configurées.
        $this->asUser()->postJson("/api/orders/{$commande->number}/cancel")->assertOk();
    }

    public function test_un_delai_a_zero_ferme_lannulation_en_ligne(): void
    {
        Setting::set(OrderCancellation::DELAY_SETTING, '0');

        [, $commande] = $this->commander();

        $this->asUser()
            ->postJson("/api/orders/{$commande->number}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('message', "L'annulation en ligne n'est pas disponible : contactez-nous et nous nous en occupons.");
    }

    // ── Autres refus ───────────────────────────────────────────────────────

    public function test_une_commande_expediee_annonce_les_frais_de_retour(): void
    {
        [, $commande] = $this->commander();
        $commande->forceFill(['status' => 'shipped'])->save();

        // Le colis roule : ce n'est plus « en préparation », et un retour se
        // paie. La cliente doit le savoir avant de nous appeler.
        $this->asUser()
            ->postJson("/api/orders/{$commande->number}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cette commande est déjà partie. Une annulation reste possible en nous contactant, mais les frais de livraison resteront à votre charge.');
    }

    public function test_une_commande_en_preparation_garde_son_message(): void
    {
        [, $commande] = $this->commander();
        $commande->forceFill(['status' => 'processing'])->save();

        $this->asUser()
            ->postJson("/api/orders/{$commande->number}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('message', "Cette commande est déjà en cours de préparation : contactez-nous pour l'annuler.");
    }

    public function test_une_commande_reglee_passe_par_un_agent(): void
    {
        [, $commande] = $this->commander();
        $commande->forceFill(['paid_at' => now()])->save();

        $this->asUser()
            ->postJson("/api/orders/{$commande->number}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cette commande est déjà réglée : contactez-nous pour son annulation et son remboursement.');
    }

    public function test_on_ne_peut_pas_annuler_la_commande_dune_autre(): void
    {
        [, $commande] = $this->commander();

        $autre = User::factory()->create();
        Sanctum::actingAs($autre);

        $this->postJson("/api/orders/{$commande->number}/cancel")->assertNotFound();
        $this->assertSame('pending', $commande->fresh()->status);
    }

    // ── Ce que le front reçoit ─────────────────────────────────────────────

    public function test_la_commande_annonce_sa_fenetre_dannulation(): void
    {
        [, $commande] = $this->commander();

        $this->asUser()
            ->getJson("/api/orders/{$commande->number}")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonPath('data.cancel_blocked_reason', null)
            ->assertJsonStructure(['data' => ['cancellable_until']]);
    }

    public function test_la_commande_explique_pourquoi_elle_nest_plus_annulable(): void
    {
        [, $commande] = $this->commander();
        $commande->forceFill(['created_at' => now()->subHours(25)])->save();

        $this->asUser()
            ->getJson("/api/orders/{$commande->number}")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', false)
            ->assertJsonPath('data.cancellable_until', null)
            ->assertJsonPath('data.cancel_blocked_reason', "Le délai d'annulation en ligne est dépassé : contactez-nous et nous nous en occupons.");
    }

    // ── Annulation décidée par la boutique ─────────────────────────────────

    public function test_la_boutique_annule_hors_delai_et_le_stock_revient(): void
    {
        [$produit, $commande] = $this->commander(qte: 3, stock: 10);
        $commande->forceFill(['created_at' => now()->subDays(5)])->save();

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/orders/{$commande->id}", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame('cancelled', $commande->fresh()->status);
        $this->assertSame(10, (int) $produit->fresh()->stock);
    }

    public function test_reappliquer_le_statut_annule_ne_recredite_pas(): void
    {
        [$produit, $commande] = $this->commander(qte: 3, stock: 10);

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/orders/{$commande->id}", ['status' => 'cancelled'])->assertOk();
        $this->putJson("/api/admin/orders/{$commande->id}", ['status' => 'cancelled'])->assertOk();

        $this->assertSame(10, (int) $produit->fresh()->stock);
        $this->assertSame(1, StockMovement::where('type', StockMovement::TYPE_CANCELLATION)->count());
    }
}
