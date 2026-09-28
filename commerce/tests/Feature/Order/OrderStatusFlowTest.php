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
use Modules\Order\Models\Order;
use Tests\Concerns\ActingAsUser;
use Tests\TestCase;

/**
 * « En attente » recouvrait deux situations : on attend le règlement de la
 * cliente, ou on attend notre propre validation. Sur une expédition, c'est la
 * différence entre relancer et préparer le colis.
 *
 * Le règlement vaut désormais confirmation sur ces destinations. Abidjan
 * garde son parcours : on y encaisse à la livraison.
 */
class OrderStatusFlowTest extends TestCase
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

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        return $admin;
    }

    private function commander(array $adresse, ?string $paiement = null): Order
    {
        $produit = Product::factory()->priced(10000)->create(['stock' => 10]);
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $produit->id, 'quantity' => 1]);

        $charge = ['shipping_address' => $adresse];
        if ($paiement) {
            $charge['payment_method'] = $paiement;
        }

        $numero = $this->asUser()->postJson('/api/orders', $charge)->assertCreated()->json('number');

        return Order::where('number', $numero)->firstOrFail();
    }

    private function expedition(): Order
    {
        return $this->commander([
            'first_name' => 'Fatou', 'last_name' => 'Koné',
            'address_line1' => 'Village de Zéguédougou',
            'city' => 'Zeguedougou-Hors-Zone', 'country' => 'CI',
            'phone' => '0102030405',
        ]);
    }

    private function abidjan(): Order
    {
        return $this->commander([
            'first_name' => 'Fatou', 'last_name' => 'Koné',
            'address_line1' => 'Cocody', 'city' => 'Abidjan',
            'commune' => 'Cocody', 'country' => 'CI',
            'phone' => '0102030405',
        ], 'cod');
    }

    // ── Libellé ────────────────────────────────────────────────────────────

    public function test_une_expedition_non_payee_attend_le_paiement(): void
    {
        $commande = $this->expedition();
        Sanctum::actingAs($this->admin());

        $this->getJson("/api/admin/orders/{$commande->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.awaiting_payment', true)
            ->assertJsonPath('data.status_label', 'En attente de paiement');
    }

    public function test_une_commande_abidjanaise_garde_son_libelle(): void
    {
        $commande = $this->abidjan();
        Sanctum::actingAs($this->admin());

        // On y encaisse à la livraison : ne rien avoir reçu est normal.
        $this->getJson("/api/admin/orders/{$commande->id}")
            ->assertOk()
            ->assertJsonPath('data.awaiting_payment', false)
            ->assertJsonPath('data.status_label', 'En attente');
    }

    // ── Le paiement vaut confirmation ──────────────────────────────────────

    public function test_marquer_payee_confirme_une_expedition(): void
    {
        $commande = $this->expedition();
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/orders/{$commande->id}/mark-paid")->assertOk();

        $commande->refresh();
        $this->assertSame('confirmed', $commande->status);
        $this->assertNotNull($commande->paid_at);
    }

    public function test_marquer_payee_ne_confirme_pas_une_commande_abidjanaise(): void
    {
        $commande = $this->abidjan();
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/orders/{$commande->id}/mark-paid")->assertOk();

        $commande->refresh();
        $this->assertSame('pending', $commande->status);
        $this->assertNotNull($commande->paid_at);
    }

    public function test_un_statut_deja_avance_nest_pas_ramene_a_confirmee(): void
    {
        $commande = $this->expedition();
        $commande->forceFill(['status' => 'processing'])->save();

        Sanctum::actingAs($this->admin());
        $this->postJson("/api/admin/orders/{$commande->id}/mark-paid")->assertOk();

        // Marquer un règlement ne doit pas faire reculer la préparation.
        $this->assertSame('processing', $commande->fresh()->status);
    }

    public function test_une_expedition_payee_nattend_plus_le_paiement(): void
    {
        $commande = $this->expedition();
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/orders/{$commande->id}/mark-paid")->assertOk();

        $this->getJson("/api/admin/orders/{$commande->id}")
            ->assertOk()
            ->assertJsonPath('data.awaiting_payment', false)
            ->assertJsonPath('data.status_label', 'Confirmée');
    }
}
