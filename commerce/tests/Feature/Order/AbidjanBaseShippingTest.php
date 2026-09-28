<?php

namespace Tests\Feature\Order;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Models\Setting;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Catalog\Models\Product;
use Modules\Delivery\Models\DeliveryZone;
use Modules\Order\Models\Order;
use Tests\Concerns\ActingAsUser;
use Tests\TestCase;

/**
 * Tarif de base d'Abidjan.
 *
 * Les communes et quartiers sont ajoutés au fil de l'eau. Une adresse
 * abidjanaise qui n'a pas encore sa zone partait en « frais à confirmer » :
 * la commande se retrouvait bloquée à l'expédition et la cliente sans moyen
 * de paiement, alors qu'on sait parfaitement livrer là-bas. Elle est
 * désormais facturée au tarif de base.
 */
class AbidjanBaseShippingTest extends TestCase
{
    use RefreshDatabase, ActingAsUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Delivery\Database\Seeders\DeliveryZoneSeeder::class);
        $this->setUpUser();
    }

    private function adresse(array $overrides = []): array
    {
        return array_merge([
            'first_name'    => 'Fatou',
            'last_name'     => 'Koné',
            'address_line1' => 'Rue du marché',
            'city'          => 'Abidjan',
            'commune'       => 'Adjamé',   // commune du district, absente du seeder
            'country'       => 'CI',
            'phone'         => '0102030405',
        ], $overrides);
    }

    private function panier(int $prix = 10000, int $qte = 1): void
    {
        $product = Product::factory()->priced($prix)->create();
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $qte]);
    }

    // ── Garde-fou : la commune choisie doit bien être hors zones ────────────

    public function test_la_commune_temoin_nest_pas_deja_tarifee(): void
    {
        $this->assertNull(DeliveryZone::quote('Abidjan', 'CI', null, 'Adjamé'));
    }

    // ── Devis ──────────────────────────────────────────────────────────────

    public function test_le_devis_applique_le_tarif_de_base(): void
    {
        $this->getJson('/api/shipping/quote?city=Abidjan&commune=Adjam%C3%A9')
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('price', 2500)
            ->assertJsonPath('fallback', true);
    }

    public function test_une_commune_tarifee_garde_son_prix(): void
    {
        $this->getJson('/api/shipping/quote?city=Abidjan&commune=Cocody')
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('price', 1500);
    }

    public function test_abidjan_sans_commune_reclame_toujours_la_commune(): void
    {
        $this->getJson('/api/shipping/quote?city=Abidjan')
            ->assertOk()
            ->assertJsonPath('found', false)
            ->assertJsonPath('needs_commune', true);
    }

    public function test_hors_abidjan_reste_sans_repli(): void
    {
        $this->getJson('/api/shipping/quote?city=Zeguedougou-Hors-Zone')
            ->assertNotFound()
            ->assertJsonPath('found', false);
    }

    // ── Commande ───────────────────────────────────────────────────────────

    public function test_la_commande_est_facturee_au_tarif_de_base(): void
    {
        $this->panier(10000, 1);

        $numero = $this->asUser()
            ->postJson('/api/orders', [
                'shipping_address' => $this->adresse(),
                'payment_method'   => 'cod',
            ])
            ->assertCreated()
            ->json('number');

        $commande = Order::where('number', $numero)->firstOrFail();

        $this->assertEquals('2500.00', $commande->shipping_cost);
        $this->assertEquals('12500.00', $commande->total);
    }

    public function test_la_commande_nest_plus_marquee_hors_zone(): void
    {
        $this->panier(10000, 1);

        $numero = $this->asUser()
            ->postJson('/api/orders', [
                'shipping_address' => $this->adresse(),
                'payment_method'   => 'cod',
            ])
            ->assertCreated()
            ->json('number');

        $commande = Order::where('number', $numero)->firstOrFail();

        // Sans ce marqueur, l'expédition n'est plus bloquée.
        $this->assertFalse((bool) ($commande->metadata['shipping_unknown'] ?? false));
    }

    public function test_le_paiement_a_la_livraison_reste_propose(): void
    {
        $this->panier(10000, 1);

        // Contradiction évitée : une adresse facturée ne doit pas être
        // annoncée « hors zone », sinon aucun moyen de paiement n'est offert.
        $this->asUser()
            ->postJson('/api/orders', [
                'shipping_address' => $this->adresse(),
                'payment_method'   => 'cod',
            ])
            ->assertCreated()
            ->assertJsonPath('payment_method', 'cod');
    }

    // ── Réglage admin ──────────────────────────────────────────────────────

    public function test_le_tarif_de_base_est_parametrable(): void
    {
        Setting::set(DeliveryZone::ABIDJAN_BASE_SETTING, '3000');

        $this->getJson('/api/shipping/quote?city=Abidjan&commune=Adjam%C3%A9')
            ->assertOk()
            ->assertJsonPath('price', 3000);
    }

    public function test_un_reglage_vide_retombe_sur_2500(): void
    {
        Setting::set(DeliveryZone::ABIDJAN_BASE_SETTING, '');

        $this->assertSame(2500, DeliveryZone::abidjanBaseCost());
    }
}
