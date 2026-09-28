<?php

namespace Tests\Feature\Delivery;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Models\Setting;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Catalog\Models\Product;
use Modules\Delivery\Models\DeliveryZone;
use Modules\Order\Support\AbidjanZone;
use Modules\Order\Support\HomeZone;
use Tests\Concerns\ActingAsUser;
use Tests\TestCase;

/**
 * Zone locale configurable (marque blanche).
 *
 * La boutique d'origine livre Abidjan ; une autre livre Bouaké. La règle ne
 * change pas — paiement à la livraison dans la zone locale, prépaiement
 * ailleurs — seule la ville change, et elle se règle dans l'admin.
 */
class HomeZoneTest extends TestCase
{
    use RefreshDatabase, ActingAsUser;

    protected function setUp(): void
    {
        parent::setUp();
        DeliveryZone::forgetAbidjanZoneNames();
    }

    private function zoneLocale(string $ville): void
    {
        Setting::set(HomeZone::SETTING, $ville);
    }

    public function test_sans_reglage_la_zone_locale_est_abidjan(): void
    {
        $this->assertSame('Abidjan', HomeZone::city());
        $this->assertTrue(AbidjanZone::isAbidjan(['city' => 'Abidjan', 'commune' => 'Cocody']));
        $this->assertFalse(AbidjanZone::isAbidjan(['city' => 'Bouaké']));
    }

    public function test_une_autre_ville_devient_la_zone_locale(): void
    {
        $this->zoneLocale('Bouaké');

        $this->assertTrue(AbidjanZone::isAbidjan(['city' => 'Bouaké']));
        $this->assertTrue(AbidjanZone::isAbidjan(['city' => 'bouake']));
        $this->assertFalse(AbidjanZone::isAbidjan(['city' => 'Abidjan']));
    }

    public function test_les_communes_d_abidjan_ne_valent_plus_hors_abidjan(): void
    {
        $this->zoneLocale('Bouaké');

        // Le socle officiel des treize communes ne vaut que pour Abidjan.
        $this->assertFalse(AbidjanZone::isAbidjan(['city' => '', 'commune' => 'Cocody']));
    }

    public function test_les_zones_du_groupe_de_la_ville_sont_locales(): void
    {
        $this->zoneLocale('Bouaké');

        DeliveryZone::create([
            'group' => 'Grand Bouaké', 'name' => 'Koko', 'country' => 'CI',
            'cities' => [], 'price' => 1000, 'price_unit' => 'fixed',
            'sort_order' => 1, 'active' => true,
        ]);

        $this->assertTrue(AbidjanZone::isAbidjan(['city' => '', 'commune' => 'Koko']));
    }

    public function test_changer_la_ville_invalide_la_valeur_memorisee(): void
    {
        $this->assertSame('Abidjan', HomeZone::city());

        $this->zoneLocale('San-Pédro');

        $this->assertSame('San-Pédro', HomeZone::city());
        $this->assertSame('Hors San-Pédro', HomeZone::outsideLabel());
    }

    public function test_la_ville_est_publique(): void
    {
        $this->zoneLocale('Yamoussoukro');

        $this->getJson('/api/settings')->assertOk()->assertJsonPath('delivery_home_city', 'Yamoussoukro');
    }

    public function test_hors_zone_locale_le_paiement_a_la_livraison_est_refuse(): void
    {
        $this->setUpUser();
        $this->zoneLocale('Bouaké');

        // Destination tarifée, mais hors de la zone locale : seul le mode de
        // paiement peut être refusé.
        DeliveryZone::create([
            'group' => 'Grand Abidjan', 'name' => 'Cocody', 'country' => 'CI',
            'cities' => [], 'price' => 1500, 'price_unit' => 'fixed',
            'sort_order' => 1, 'active' => true,
        ]);

        $product = Product::factory()->priced(10000)->create();
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        $response = $this->actingAs($this->user)->postJson('/api/orders', [
            'payment_method'   => 'cod',
            'shipping_address' => [
                'first_name'    => 'Awa',
                'last_name'     => 'Koné',
                'address_line1' => 'Rue 12',
                'city'          => 'Abidjan',
                'commune'       => 'Cocody',
                'country'       => 'CI',
                'phone'         => '0102030405',
            ],
        ]);

        // Abidjan est l'intérieur du pays pour une boutique de Bouaké.
        $response->assertJsonValidationErrors('payment_method');
    }
}
