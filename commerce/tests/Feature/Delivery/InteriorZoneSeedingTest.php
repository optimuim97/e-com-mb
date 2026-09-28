<?php

namespace Tests\Feature\Delivery;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Delivery\Database\Seeders\DeliveryZoneSeeder;
use Modules\Delivery\Database\Seeders\InteriorDeliveryZoneSeeder;
use Modules\Delivery\Models\DeliveryZone;
use Tests\TestCase;

/**
 * Tarification de l'intérieur du pays.
 *
 * Douze villes seulement étaient tarifées : partout ailleurs la commande
 * partait en « frais à confirmer » et restait bloquée à l'expédition. Le
 * seeder complète le barème sans jamais écraser ce qui existe — c'est la
 * condition pour pouvoir le rejouer après un ajustement fait en administration.
 */
class InteriorZoneSeedingTest extends TestCase
{
    use RefreshDatabase;

    private function tarifCI(string $ville, ?string $commune = null): ?array
    {
        return DeliveryZone::quote($ville, 'CI', null, $commune);
    }

    public function test_les_destinations_manquantes_sont_tarifees(): void
    {
        $this->seed(DeliveryZoneSeeder::class);

        // Avant : aucune zone ne couvre ces départements.
        $this->assertNull($this->tarifCI('Agboville'));
        $this->assertNull($this->tarifCI('Odienné'));
        $this->assertNull($this->tarifCI('Bondoukou'));

        $this->seed(InteriorDeliveryZoneSeeder::class);

        foreach (['Agboville', 'Odienné', 'Bondoukou', 'Tengréla', 'Soubré'] as $ville) {
            $devis = $this->tarifCI($ville);
            $this->assertNotNull($devis, "« {$ville} » est resté sans tarif.");
            $this->assertSame(3000, (int) $devis['price']);
        }
    }

    public function test_les_tarifs_existants_ne_sont_pas_ecrases(): void
    {
        $this->seed(DeliveryZoneSeeder::class);
        $this->seed(InteriorDeliveryZoneSeeder::class);

        // Bouaké et Korhogo étaient déjà à 2 500 : le tarif unique de 3 000 ne
        // doit pas les avoir alignés vers le haut.
        $this->assertSame(2500, (int) $this->tarifCI('Bouaké')['price']);
        $this->assertSame(2500, (int) $this->tarifCI('Korhogo')['price']);
        $this->assertSame(3000, (int) $this->tarifCI('Sassandra')['price']);
    }

    public function test_aucun_doublon_nest_cree(): void
    {
        $this->seed(DeliveryZoneSeeder::class);
        $this->seed(InteriorDeliveryZoneSeeder::class);

        // Dédoublonné par zone d'abord : le barème répète souvent le nom dans
        // ses propres alias (« Bouaké » + alias « bouake »), ce qui n'est pas
        // un conflit. Seule compte une appellation revendiquée par deux zones
        // différentes — là, le tarif rendu dépend de l'ordre de tri.
        $doublons = DeliveryZone::where('country', 'CI')->get()
            ->flatMap(fn (DeliveryZone $z) => collect([$z->name, ...($z->cities ?? [])])
                ->map(fn ($n) => DeliveryZone::normalize((string) $n))
                ->unique()
                ->values())
            ->duplicates()
            ->values()
            ->all();

        $this->assertSame([], $doublons, 'Des destinations sont couvertes deux fois : ' . implode(', ', $doublons));
    }

    public function test_les_orthographes_divergentes_ne_creent_pas_de_seconde_zone(): void
    {
        $this->seed(DeliveryZoneSeeder::class);
        $this->seed(InteriorDeliveryZoneSeeder::class);

        // Le barème écrit « San Pedro » et « Grand Lahou », le découpage
        // administratif « San-Pédro » et « Grand-Lahou ». Seuls les alias des
        // zones existantes permettent de reconnaître qu'il s'agit des mêmes
        // destinations.
        $this->assertSame(1, DeliveryZone::where('country', 'CI')
            ->get()
            ->filter(fn (DeliveryZone $z) => str_contains(DeliveryZone::normalize($z->name), 'pedro'))
            ->count());

        $this->assertSame(1, DeliveryZone::where('country', 'CI')
            ->get()
            ->filter(fn (DeliveryZone $z) => str_contains(DeliveryZone::normalize($z->name), 'lahou'))
            ->count());
    }

    public function test_le_seeder_est_rejouable(): void
    {
        $this->seed(DeliveryZoneSeeder::class);
        $this->seed(InteriorDeliveryZoneSeeder::class);

        $apresPremier = DeliveryZone::where('country', 'CI')->count();

        // Un tarif ajusté à la main entre deux passages doit survivre.
        $zone = DeliveryZone::where('country', 'CI')->where('name', 'Agboville')->firstOrFail();
        $zone->update(['price' => 1800]);

        $this->seed(InteriorDeliveryZoneSeeder::class);

        $this->assertSame($apresPremier, DeliveryZone::where('country', 'CI')->count());
        $this->assertSame(1800, (int) $zone->fresh()->price);
    }

    public function test_abidjan_garde_son_bareme(): void
    {
        $this->seed(DeliveryZoneSeeder::class);
        $this->seed(InteriorDeliveryZoneSeeder::class);

        // Abidjan est absent de la liste : ses communes relèvent du groupe
        // « Grand Abidjan », qui a ses propres tarifs.
        $this->assertSame(1500, (int) $this->tarifCI('Abidjan', 'Cocody')['price']);
        $this->assertSame(3000, (int) $this->tarifCI('Abidjan', 'Grand-Bassam')['price']);

        $this->assertSame(0, DeliveryZone::where('group', 'Intérieur CI')
            ->whereIn('name', ['Abidjan', 'Cocody', 'Yopougon'])
            ->count());
    }

    public function test_une_commune_de_linterieur_suit_le_tarif_de_son_departement(): void
    {
        $this->seed(DeliveryZoneSeeder::class);
        $this->seed(InteriorDeliveryZoneSeeder::class);

        // La boutique envoie le département en ville et la sous-préfecture en
        // commune : aucune zone ne porte « Rubino », c'est « Agboville » qui
        // doit répondre.
        $devis = $this->tarifCI('Agboville', 'Rubino');

        $this->assertNotNull($devis);
        $this->assertSame(3000, (int) $devis['price']);
    }
}
