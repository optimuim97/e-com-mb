<?php

namespace Tests\Feature\Delivery;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Delivery\Database\Seeders\DeliveryZoneSeeder;
use Modules\Delivery\Models\DeliveryZone;
use Modules\Order\Support\AbidjanZone;
use Modules\Order\Support\PaymentAvailability;
use Tests\TestCase;

/**
 * Le district livré est plus large que le découpage administratif. Abatta,
 * Faya, Riviera sont tarifés dans les zones mais n'ont jamais été des communes
 * officielles : décrits en dur, ils basculaient la commande en « intérieur du
 * pays » — prépaiement Wave/Orange imposé et expédition bloquée, pour une
 * adresse que le livreur dessert.
 *
 * Les zones du groupe « Grand Abidjan » font désormais foi.
 */
class AbidjanZoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DeliveryZoneSeeder::class);
        DeliveryZone::forgetAbidjanZoneNames();
    }

    public function test_un_quartier_livre_est_dans_abidjan(): void
    {
        foreach (['Abatta', 'Faya', 'Riviera', 'Angré', "N'Dotré", 'Gonzagueville'] as $commune) {
            $this->assertTrue(
                AbidjanZone::isAbidjan(['city' => '', 'commune' => $commune]),
                "$commune devrait être reconnue comme abidjanaise",
            );
        }
    }

    public function test_les_communes_officielles_restent_reconnues(): void
    {
        foreach (['Cocody', 'Yopougon', 'Port-Bouët', 'Treichville'] as $commune) {
            $this->assertTrue(AbidjanZone::isAbidjan(['city' => 'Abidjan', 'commune' => $commune]));
        }
    }

    public function test_une_ville_de_linterieur_reste_hors_abidjan(): void
    {
        $this->assertFalse(AbidjanZone::isAbidjan(['city' => 'Bouaké', 'commune' => '']));
        $this->assertFalse(AbidjanZone::isAbidjan(['city' => 'Yamoussoukro', 'commune' => '']));
    }

    public function test_un_quartier_livre_donne_droit_au_paiement_a_la_livraison(): void
    {
        $adresse = ['country' => 'CI', 'city' => 'Abidjan', 'commune' => 'Abatta'];

        $this->assertSame(PaymentAvailability::ABIDJAN, PaymentAvailability::destination($adresse));
        $this->assertSame(['cod', 'delivery'], PaymentAvailability::allowed($adresse));
    }

    public function test_la_commune_dans_le_champ_ville_est_reconnue(): void
    {
        // Géolocalisation et import POS remplissent parfois la ville seule.
        $this->assertTrue(AbidjanZone::isAbidjan(['city' => 'Abatta', 'commune' => '']));
    }

    public function test_une_zone_ajoutee_est_prise_en_compte_sans_redemarrage(): void
    {
        $adresse = ['country' => 'CI', 'city' => '', 'commune' => 'Adjouffou'];
        $this->assertFalse(AbidjanZone::isAbidjan($adresse));

        DeliveryZone::create([
            'group'   => 'Grand Abidjan',
            'name'    => 'Adjouffou',
            'country' => 'CI',
            'price'   => 2000,
            'active'  => true,
        ]);

        $this->assertTrue(
            AbidjanZone::isAbidjan($adresse),
            'Le cache doit tomber à la création d\'une zone',
        );
    }

    public function test_le_groupe_est_reconnu_quelle_que_soit_la_casse(): void
    {
        $this->assertTrue(DeliveryZone::isAbidjanGroup('Grand Abidjan'));
        $this->assertTrue(DeliveryZone::isAbidjanGroup('grand abidjan'));
        $this->assertTrue(DeliveryZone::isAbidjanGroup('Abidjan'));
        $this->assertFalse(DeliveryZone::isAbidjanGroup('Intérieur CI'));
        $this->assertFalse(DeliveryZone::isAbidjanGroup(null));
    }

    public function test_une_zone_desactivee_ne_compte_plus(): void
    {
        $zone = DeliveryZone::where('name', 'Abatta')->firstOrFail();
        $zone->update(['active' => false]);

        $this->assertFalse(AbidjanZone::isAbidjan(['city' => '', 'commune' => 'Abatta']));
    }
}
