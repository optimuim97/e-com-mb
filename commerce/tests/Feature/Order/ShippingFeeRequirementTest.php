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
 * Frais de livraison à fixer avant d'annoncer un montant.
 *
 * À Abidjan, les frais sont connus dès la commande. Hors Abidjan et à
 * l'international, non : ils dépendent du transporteur, du poids, parfois de
 * la douane. Annoncer un total avant de les avoir arrêtés oblige à se dédire —
 * ou à livrer à perte.
 */
class ShippingFeeRequirementTest extends TestCase
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

    /** Commande vers une ville de l'intérieur non tarifée. */
    private function commanderHorsAbidjan(): Order
    {
        $produit = Product::factory()->priced(10000)->create(['stock' => 10]);
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $produit->id, 'quantity' => 1]);

        $numero = $this->asUser()
            ->postJson('/api/orders', [
                'shipping_address' => [
                    'first_name' => 'Fatou', 'last_name' => 'Koné',
                    'address_line1' => 'Village de Zéguédougou',
                    'city' => 'Zeguedougou-Hors-Zone', 'country' => 'CI',
                    'phone' => '0102030405',
                ],
            ])
            ->assertCreated()
            ->json('number');

        return Order::where('number', $numero)->firstOrFail();
    }

    /** Commande abidjanaise, tarifée d'office. */
    private function commanderAbidjan(): Order
    {
        $produit = Product::factory()->priced(10000)->create(['stock' => 10]);
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $produit->id, 'quantity' => 1]);

        $numero = $this->asUser()
            ->postJson('/api/orders', [
                'shipping_address' => [
                    'first_name' => 'Fatou', 'last_name' => 'Koné',
                    'address_line1' => 'Cocody', 'city' => 'Abidjan',
                    'commune' => 'Cocody', 'country' => 'CI',
                    'phone' => '0102030405',
                ],
                'payment_method' => 'cod',
            ])
            ->assertCreated()
            ->json('number');

        return Order::where('number', $numero)->firstOrFail();
    }

    // ── Ce que l'écran reçoit ──────────────────────────────────────────────

    public function test_une_commande_hors_abidjan_annonce_ses_frais_a_fixer(): void
    {
        $commande = $this->commanderHorsAbidjan();
        Sanctum::actingAs($this->admin());

        $this->getJson("/api/admin/orders/{$commande->id}")
            ->assertOk()
            ->assertJsonPath('data.shipping_fee_pending', true)
            ->assertJsonPath('data.shipping_fee_editable', true);
    }

    public function test_une_commande_abidjanaise_na_rien_a_fixer(): void
    {
        $commande = $this->commanderAbidjan();
        Sanctum::actingAs($this->admin());

        $this->getJson("/api/admin/orders/{$commande->id}")
            ->assertOk()
            ->assertJsonPath('data.shipping_fee_pending', false)
            // Le champ n'est pas non plus « toujours modifiable » : Abidjan est
            // tarifée par les zones.
            ->assertJsonPath('data.shipping_fee_editable', false);
    }

    public function test_le_champ_reste_modifiable_une_fois_les_frais_saisis(): void
    {
        $commande = $this->commanderHorsAbidjan();
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/admin/orders/{$commande->id}", ['shipping_cost' => 3000])->assertOk();

        // Le transporteur peut revoir son prix : la porte reste ouverte.
        $this->getJson("/api/admin/orders/{$commande->id}")
            ->assertOk()
            ->assertJsonPath('data.shipping_fee_pending', false)
            ->assertJsonPath('data.shipping_fee_editable', true);
    }

    // ── Garde-fous ─────────────────────────────────────────────────────────

    public function test_notifier_est_refuse_tant_que_les_frais_manquent(): void
    {
        $commande = $this->commanderHorsAbidjan();
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/orders/{$commande->id}/notify")
            ->assertStatus(422)
            ->assertJsonPath('code', 'shipping_fee_required');
    }

    public function test_le_lien_de_paiement_est_refuse_tant_que_les_frais_manquent(): void
    {
        $commande = $this->commanderHorsAbidjan();
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/admin/orders/{$commande->id}/payment-link")
            ->assertStatus(422)
            ->assertJsonPath('code', 'shipping_fee_required');
    }

    public function test_lexpedition_est_refusee_tant_que_les_frais_manquent(): void
    {
        $commande = $this->commanderHorsAbidjan();
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/admin/orders/{$commande->id}", ['status' => 'shipped'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'shipping_blocked');
    }

    // ── Une fois les frais saisis ──────────────────────────────────────────

    public function test_saisir_les_frais_recalcule_le_total_et_debloque(): void
    {
        $commande = $this->commanderHorsAbidjan();
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/admin/orders/{$commande->id}", ['shipping_cost' => 3500])->assertOk();

        $commande->refresh();
        $this->assertEquals('3500.00', $commande->shipping_cost);
        $this->assertEquals('13500.00', $commande->total);
        $this->assertFalse((bool) ($commande->metadata['shipping_unknown'] ?? false));

        // Le lien de paiement ne bute plus sur les frais. Il peut encore
        // échouer pour une autre raison (passerelle non configurée en test) :
        // ce qu'on vérifie, c'est que ce n'est plus CE motif-là.
        $reponse = $this->postJson("/api/admin/orders/{$commande->id}/payment-link");
        $this->assertNotSame('shipping_fee_required', $reponse->json('code'));

        $this->getJson("/api/admin/orders/{$commande->id}")
            ->assertOk()
            ->assertJsonPath('data.shipping_fee_pending', false);
    }

    public function test_des_frais_a_zero_ne_valent_pas_des_frais_saisis(): void
    {
        $commande = $this->commanderHorsAbidjan();
        Sanctum::actingAs($this->admin());

        // Le marqueur tombe, mais un total de transport nul reste un total
        // non fixé : aucune livraison n'est gratuite dans cette boutique.
        $this->putJson("/api/admin/orders/{$commande->id}", ['shipping_cost' => 0])->assertOk();

        $this->getJson("/api/admin/orders/{$commande->id}")
            ->assertOk()
            ->assertJsonPath('data.shipping_fee_pending', true);
    }

    public function test_labidjanaise_se_notifie_sans_blocage(): void
    {
        $commande = $this->commanderAbidjan();
        Sanctum::actingAs($this->admin());

        // Pas de 422 « frais à renseigner » : Abidjan est tarifée d'office.
        $reponse = $this->postJson("/api/admin/orders/{$commande->id}/notify");
        $this->assertNotSame('shipping_fee_required', $reponse->json('code'));
    }
}
