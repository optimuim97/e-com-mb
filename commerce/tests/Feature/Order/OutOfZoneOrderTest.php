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
 * Commande vers une destination non tarifée.
 *
 * La cliente doit pouvoir finaliser sans choisir ni frais de livraison ni
 * moyen de paiement : le montant n'est pas encore connu, donc rien ne peut
 * être réglé. Les agents fixent ensuite les frais depuis l'administration,
 * puis conviennent du règlement.
 */
class OutOfZoneOrderTest extends TestCase
{
    use RefreshDatabase, ActingAsUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUser();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
    }

    /** Destination volontairement absente des zones configurées. */
    private function adresseHorsZone(): array
    {
        return [
            'first_name'    => 'Fatou',
            'last_name'     => 'Koné',
            'address_line1' => 'Village de Zéguédougou',
            'city'          => 'Zeguedougou-Hors-Zone',
            'country'       => 'CI',
            'phone'         => '0102030405',
        ];
    }

    private function panier(int $prix = 10000, int $qte = 2): void
    {
        $product = Product::factory()->priced($prix)->create();
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $qte]);
    }

    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->syncRoles(['admin']);
        return $agent;
    }

    // ── Côté cliente ───────────────────────────────────────────────────────

    public function test_la_commande_aboutit_sans_moyen_de_paiement(): void
    {
        $this->panier(10000, 2);

        $this->asUser()
            ->postJson('/api/orders', ['shipping_address' => $this->adresseHorsZone()])
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            // Frais à 0 pour l'instant : le total ne couvre que les articles.
            ->assertJsonPath('total', '20000.00');
    }

    public function test_la_commande_est_marquee_frais_a_renseigner(): void
    {
        $this->panier(10000, 1);

        $numero = $this->asUser()
            ->postJson('/api/orders', ['shipping_address' => $this->adresseHorsZone()])
            ->assertCreated()
            ->json('number');

        $order = Order::where('number', $numero)->firstOrFail();

        $this->assertTrue((bool) ($order->metadata['shipping_unknown'] ?? false),
            'La commande hors zone doit porter le marqueur shipping_unknown.');
        $this->assertSame(0.0, (float) $order->shipping_cost);
        $this->assertNull($order->payment_method);
    }

    public function test_choisir_un_moyen_de_paiement_hors_zone_est_refuse(): void
    {
        // Sans ce refus, la cliente réglerait un montant qui ne comprend pas
        // encore les frais de livraison.
        $this->panier();

        $this->asUser()
            ->postJson('/api/orders', [
                'shipping_address' => $this->adresseHorsZone(),
                'payment_method'   => 'wave',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');
    }

    public function test_en_zone_couverte_le_moyen_de_paiement_reste_obligatoire(): void
    {
        // Le relâchement ne doit valoir que hors zone : ailleurs, laisser
        // passer une commande sans règlement serait une régression.
        \Modules\Delivery\Models\DeliveryZone::create([
            'group'   => 'Grand Abidjan',
            'name'    => 'Cocody',
            'country' => 'CI',
            'cities'  => ['Cocody'],
            'price'   => 1500,
            'active'  => true,
        ]);

        $this->panier();

        $this->asUser()
            ->postJson('/api/orders', [
                'shipping_address' => array_merge($this->adresseHorsZone(), [
                    'city'    => 'Cocody',
                    'commune' => 'Cocody',
                ]),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');
    }

    // ── Côté agent ─────────────────────────────────────────────────────────

    public function test_l_agent_fixe_les_frais_et_le_total_est_recalcule(): void
    {
        $this->panier(10000, 2);
        $numero = $this->asUser()
            ->postJson('/api/orders', ['shipping_address' => $this->adresseHorsZone()])
            ->json('number');
        $order = Order::where('number', $numero)->firstOrFail();

        Sanctum::actingAs($this->agent());
        $this->putJson("/api/admin/orders/{$order->id}", ['shipping_cost' => 3500])
            ->assertOk();

        $order->refresh();
        $this->assertSame(3500.0, (float) $order->shipping_cost);
        $this->assertSame(23500.0, (float) $order->total, 'Le total doit intégrer les frais saisis.');
        $this->assertFalse((bool) ($order->metadata['shipping_unknown'] ?? false),
            'Le marqueur doit disparaître une fois les frais renseignés.');
    }

    public function test_on_n_expedie_pas_une_commande_dont_les_frais_manquent(): void
    {
        // Garde-fou : expédier sans frais fixés revient à livrer gratuitement
        // sans que personne l'ait décidé.
        $this->panier();
        $numero = $this->asUser()
            ->postJson('/api/orders', ['shipping_address' => $this->adresseHorsZone()])
            ->json('number');
        $order = Order::where('number', $numero)->firstOrFail();

        Sanctum::actingAs($this->agent());
        $this->putJson("/api/admin/orders/{$order->id}", ['status' => 'shipped'])
            ->assertStatus(422);

        $this->assertSame('pending', $order->refresh()->status);
    }

    public function test_l_expedition_passe_une_fois_les_frais_renseignes(): void
    {
        $this->panier();
        $numero = $this->asUser()
            ->postJson('/api/orders', ['shipping_address' => $this->adresseHorsZone()])
            ->json('number');
        $order = Order::where('number', $numero)->firstOrFail();

        Sanctum::actingAs($this->agent());
        $this->putJson("/api/admin/orders/{$order->id}", ['shipping_cost' => 2000])->assertOk();

        // Les frais ne lèvent qu'un des deux verrous : une commande impayée
        // reste bloquée, ce qui est le comportement voulu.
        $this->putJson("/api/admin/orders/{$order->id}", ['status' => 'shipped'])
            ->assertStatus(422)
            ->assertJsonPath('blockers', ["La commande n'est pas payée."]);

        $this->postJson("/api/admin/orders/{$order->id}/mark-paid")->assertOk();
        $this->putJson("/api/admin/orders/{$order->id}", ['status' => 'shipped'])->assertOk();

        $this->assertSame('shipped', $order->refresh()->status);
    }
}
